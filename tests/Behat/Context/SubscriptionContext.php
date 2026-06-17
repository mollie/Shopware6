<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Doctrine\DBAL\Connection;
use Mollie\Shopware\Behat\Storage;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGatewayInterface;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\PriceDrift\PriceDriftDetector;
use Mollie\Shopware\Component\Subscription\PriceDrift\PriceMigrationHandler;
use Mollie\Shopware\Component\Subscription\Route\WebhookRoute;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandler;
use Mollie\Shopware\Integration\Data\CustomerTestBehaviour;
use Mollie\Shopware\Integration\Data\ProductTestBehaviour;
use Mollie\Shopware\Integration\Data\SubscriptionTestBehaviour;
use PHPUnit\Framework\Assert;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

final class SubscriptionContext extends ShopwareContext
{
    use SubscriptionTestBehaviour;
    use CustomerTestBehaviour;
    use ProductTestBehaviour;
    public const STORAGE_SUBSCRIPTION = 'subscription';
    public const STORAGE_REMEMBERED_SUBSCRIPTION_ID = 'rememberedSubscriptionId';
    public const STORAGE_PRODUCT_PRICE_BACKUP = 'productPriceBackup';

    #[Then('i :arg1 the subscription')]
    public function iTheSubscription(string $action): void
    {
        /** @var SubscriptionEntity $subscription */
        $subscription = Storage::get(self::STORAGE_SUBSCRIPTION);

        $context = $this->getCurrentSalesChannelContext()->getContext();
        /** @var SubscriptionActionHandler $actionHandler */
        $actionHandler = $this->getContainer()->get(SubscriptionActionHandler::class);
        $actionHandler->handle($action,$subscription->getId(),$context);
    }

    #[Then('the subscription status is :arg1')]
    public function theSubscriptionStatusIs(string $status): void
    {
        $orderId = Storage::get('orderId');
        $context = $this->getCurrentSalesChannelContext()->getContext();

        $subscription = $this->getOrderSubscriptions($orderId, $context)->first();

        Assert::assertNotNull($subscription, sprintf('No subscription found for order %s', $orderId));

        Storage::set(self::STORAGE_SUBSCRIPTION, $subscription);

        Assert::assertSame($status, $subscription->getStatus());
    }

    #[Then('the mollie subscription reports :arg1 times remaining')]
    public function theMollieSubscriptionReportsTimesRemaining(string $expected): void
    {
        /** @var SubscriptionEntity $subscription */
        $subscription = Storage::get(self::STORAGE_SUBSCRIPTION);

        $mollieId = $subscription->getMollieId();
        $mollieCustomerId = $subscription->getMollieCustomerId();
        $salesChannelId = $subscription->getSalesChannelId();

        Assert::assertNotEmpty($mollieId, sprintf('Local subscription %s has no Mollie id yet', $subscription->getId()));
        Assert::assertNotEmpty($mollieCustomerId, sprintf('Local subscription %s has no Mollie customer id', $subscription->getId()));
        Assert::assertNotNull($salesChannelId, sprintf('Local subscription %s has no sales channel id', $subscription->getId()));

        /** @var SubscriptionGatewayInterface $gateway */
        $gateway = $this->getContainer()->get(SubscriptionGateway::class);
        $mollieSubscription = $gateway->getSubscription($mollieId, $mollieCustomerId, '', $salesChannelId);

        Assert::assertSame(
            (int) $expected,
            $mollieSubscription->getTimesRemaining(),
            sprintf('Expected timesRemaining=%s on Mollie subscription %s, got %s', $expected, $mollieId, var_export($mollieSubscription->getTimesRemaining(), true))
        );
    }

    #[Then('all subscriptions of the order have a mollie id')]
    public function allSubscriptionsOfTheOrderHaveAMollieId(): void
    {
        $orderId = Storage::get('orderId');
        $context = $this->getCurrentSalesChannelContext()->getContext();

        $subscriptions = $this->getOrderSubscriptions($orderId, $context);

        Assert::assertSame(2, $subscriptions->count(), sprintf('Expected 2 subscriptions for order %s, got %d', $orderId, $subscriptions->count()));

        foreach ($subscriptions as $subscription) {
            Assert::assertNotEmpty(
                $subscription->getMollieId(),
                sprintf('Subscription %s has no Mollie id', $subscription->getId())
            );
        }
    }

    #[Then('i remember the subscription for renewal')]
    public function iRememberTheSubscriptionForRenewal(): void
    {
        $orderId = Storage::get(CheckoutContext::STORAGE_ORDER_ID);
        $context = $this->getCurrentSalesChannelContext()->getContext();

        $subscription = $this->getOrderSubscriptions($orderId, $context)->first();
        Assert::assertNotNull($subscription, sprintf('No subscription found for order %s', $orderId));

        Storage::set(self::STORAGE_REMEMBERED_SUBSCRIPTION_ID, $subscription->getId());
    }

    #[Then('i remember the subscription with interval :arg1 for renewal')]
    public function iRememberTheSubscriptionWithIntervalForRenewal(string $interval): void
    {
        $orderId = Storage::get(CheckoutContext::STORAGE_ORDER_ID);
        $context = $this->getCurrentSalesChannelContext()->getContext();

        $subscriptions = $this->getOrderSubscriptions($orderId, $context);

        $matched = null;
        foreach ($subscriptions as $subscription) {
            if ((string) $subscription->getMetadata()->getInterval() === $interval) {
                $matched = $subscription;
                break;
            }
        }

        Assert::assertNotNull($matched, sprintf('No subscription with interval "%s" found for order %s', $interval, $orderId));

        Storage::set(self::STORAGE_REMEMBERED_SUBSCRIPTION_ID, $matched->getId());
    }

    #[When('i change the subscription shipping address to country :arg1')]
    public function iChangeTheSubscriptionShippingAddressToCountry(string $isoCode): void
    {
        $subscriptionId = Storage::get(self::STORAGE_REMEMBERED_SUBSCRIPTION_ID);
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $context = $salesChannelContext->getContext();

        $addressIdResult = $this->getUserAddressByIso($isoCode, $salesChannelContext);
        $customerAddressId = $addressIdResult->firstId();
        Assert::assertNotEmpty($customerAddressId, sprintf('Customer has no address with country %s', $isoCode));

        /** @var EntityRepository $customerAddressRepository */
        $customerAddressRepository = $this->getContainer()->get('customer_address.repository');
        /** @var ?CustomerAddressEntity $customerAddress */
        $customerAddress = $customerAddressRepository->search(new Criteria([$customerAddressId]), $context)->first();
        Assert::assertNotNull($customerAddress, sprintf('Customer address %s not found', $customerAddressId));

        /** @var EntityRepository $subscriptionRepository */
        $subscriptionRepository = $this->getContainer()->get('mollie_subscription.repository');
        $criteria = new Criteria([$subscriptionId]);
        $criteria->addAssociation('shippingAddress');
        /** @var ?SubscriptionEntity $subscription */
        $subscription = $subscriptionRepository->search($criteria, $context)->first();
        Assert::assertNotNull($subscription, sprintf('Subscription %s not found', $subscriptionId));

        $shippingAddress = $subscription->getShippingAddress();
        Assert::assertInstanceOf(SubscriptionAddressEntity::class, $shippingAddress, sprintf('Subscription %s has no shipping address', $subscriptionId));

        /** @var EntityRepository $subscriptionAddressRepository */
        $subscriptionAddressRepository = $this->getContainer()->get('mollie_subscription_address.repository');
        $subscriptionAddressRepository->upsert([[
            'id' => $shippingAddress->getId(),
            'salutationId' => $customerAddress->getSalutationId(),
            'firstName' => $customerAddress->getFirstName(),
            'lastName' => $customerAddress->getLastName(),
            'company' => $customerAddress->getCompany(),
            'department' => $customerAddress->getDepartment(),
            'vatId' => null,
            'street' => $customerAddress->getStreet(),
            'zipcode' => (string) $customerAddress->getZipcode(),
            'city' => $customerAddress->getCity(),
            'countryId' => $customerAddress->getCountryId(),
            'countryStateId' => $customerAddress->getCountryStateId(),
            'phoneNumber' => $customerAddress->getPhoneNumber(),
            'additionalAddressLine1' => $customerAddress->getAdditionalAddressLine1(),
            'additionalAddressLine2' => $customerAddress->getAdditionalAddressLine2(),
        ]], $context);
    }

    #[When('i trigger the subscription renewal webhook')]
    public function iTriggerTheSubscriptionRenewalWebhook(): void
    {
        $subscriptionId = Storage::get(self::STORAGE_REMEMBERED_SUBSCRIPTION_ID);
        $paymentId = Storage::get(CheckoutContext::STORAGE_REMEMBERED_PAYMENT_ID);

        $context = $this->getCurrentSalesChannelContext()->getContext();

        /** @var WebhookRoute $webhookRoute */
        $webhookRoute = $this->getContainer()->get(WebhookRoute::class);

        $request = new Request();
        $request->query->set('id', $paymentId);

        $response = $webhookRoute->notify($subscriptionId, $request, $context);

        $renewalTransaction = $response->getPayment()->getShopwareTransaction();
        $renewalOrderId = $renewalTransaction->getOrderId();

        Assert::assertNotEmpty($renewalOrderId, 'Renewal transaction has no order id');

        Storage::set(CheckoutContext::STORAGE_ORDER_ID, $renewalOrderId);
    }

    #[Then('the subscription has been renewed')]
    public function theSubscriptionHasBeenRenewed(): void
    {
        $subscriptionId = Storage::get(self::STORAGE_REMEMBERED_SUBSCRIPTION_ID);
        $context = $this->getCurrentSalesChannelContext()->getContext();

        /** @var EntityRepository<SubscriptionCollection<SubscriptionEntity>> $repository */
        $repository = $this->getContainer()->get('mollie_subscription.repository');

        $criteria = new Criteria([$subscriptionId]);
        $criteria->addAssociation('historyEntries');

        /** @var ?SubscriptionEntity $subscription */
        $subscription = $repository->search($criteria, $context)->first();
        Assert::assertNotNull($subscription, sprintf('Subscription %s not found', $subscriptionId));

        $renewed = false;
        foreach ($subscription->getHistoryEntries() as $entry) {
            if ($entry->getComment() === 'renewed') {
                $renewed = true;
                break;
            }
        }

        Assert::assertTrue($renewed, sprintf('Subscription %s has no "renewed" history entry', $subscriptionId));
    }

    #[Given('the subscriptions price update mode is :arg1')]
    public function theSubscriptionsPriceUpdateModeIs(string $mode): void
    {
        $this->writeSystemConfig(SubscriptionSettings::KEY_PRICE_UPDATE_MODE, $mode);
    }

    #[Given('the subscriptions price update notice days is :arg1')]
    public function theSubscriptionsPriceUpdateNoticeDaysIs(string $days): void
    {
        $this->writeSystemConfig(SubscriptionSettings::KEY_PRICE_UPDATE_NOTICE_DAYS, (int) $days);
    }

    #[When('i change the price of product :arg1 to :arg2')]
    public function iChangeThePriceOfProductTo(string $productNumber, string $newPrice): void
    {
        $context = $this->getCurrentSalesChannelContext()->getContext();
        $product = $this->getProductByNumber($productNumber, $context);

        $touched = Storage::get(self::STORAGE_PRODUCT_PRICE_BACKUP) ?? [];
        $touched[$productNumber] = true;
        Storage::set(self::STORAGE_PRODUCT_PRICE_BACKUP, $touched);

        /** @var EntityRepository $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->update([[
            'id' => $product->getId(),
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => (float) $newPrice,
                'net' => (float) $newPrice,
                'linked' => true,
            ]],
        ]], $context);
    }

    /**
     * Restore subscription fixture prices the test mutated. Behat does not wrap
     * scenarios in DB transactions like PHPUnit does, so price changes leak
     * across scenarios — most importantly into the renewal scenario that
     * asserts a hardcoded order total.
     */
    #[AfterScenario]
    public function restoreChangedProductPrices(): void
    {
        $touched = Storage::get(self::STORAGE_PRODUCT_PRICE_BACKUP);
        if (! is_array($touched) || $touched === []) {
            return;
        }

        $fixturePrices = [
            'MOL_SUB_1' => 19.0,
            'MOL_SUB_2' => 29.0,
        ];

        $context = $this->getCurrentSalesChannelContext()->getContext();
        /** @var EntityRepository $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');

        $payload = [];
        foreach (array_keys($touched) as $productNumber) {
            if (! isset($fixturePrices[$productNumber])) {
                continue;
            }
            try {
                $product = $this->getProductByNumber($productNumber, $context);
            } catch (\Throwable) {
                continue;
            }

            $price = $fixturePrices[$productNumber];
            $payload[] = [
                'id' => $product->getId(),
                'price' => [[
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $price,
                    'net' => $price,
                    'linked' => true,
                ]],
            ];
        }

        if ($payload !== []) {
            $productRepository->update($payload, $context);
        }
    }

    #[When('the subscription price drift detector runs')]
    public function theSubscriptionPriceDriftDetectorRuns(): void
    {
        $this->isolateStoredSubscription();

        /** @var PriceDriftDetector $detector */
        $detector = $this->getContainer()->get(PriceDriftDetector::class);
        $detector->detect($this->getCurrentSalesChannelContext()->getContext());
    }

    #[When('the subscription price migration handler runs')]
    public function theSubscriptionPriceMigrationHandlerRuns(): void
    {
        $this->isolateStoredSubscription();

        /** @var PriceMigrationHandler $handler */
        $handler = $this->getContainer()->get(PriceMigrationHandler::class);
        $handler->migrate($this->getCurrentSalesChannelContext()->getContext());
    }

    #[Then('the subscription price update state is :arg1')]
    public function theSubscriptionPriceUpdateStateIs(string $expectedState): void
    {
        $subscription = $this->reloadStoredSubscription();
        Assert::assertSame(
            $expectedState,
            $subscription->getPriceUpdateState(),
            sprintf('Subscription %s priceUpdateState mismatch', $subscription->getId())
        );
    }

    #[Then('the subscription next notified price is :arg1')]
    public function theSubscriptionNextNotifiedPriceIs(string $expected): void
    {
        $subscription = $this->reloadStoredSubscription();
        Assert::assertSame(
            (float) $expected,
            $subscription->getNextNotifiedPrice(),
            sprintf('Subscription %s nextNotifiedPrice mismatch', $subscription->getId())
        );
    }

    #[Then('the subscription amount is :arg1')]
    public function theSubscriptionAmountIs(string $expected): void
    {
        $subscription = $this->reloadStoredSubscription();
        Assert::assertSame(
            (float) $expected,
            $subscription->getAmount(),
            sprintf('Subscription %s amount mismatch', $subscription->getId())
        );
    }

    #[Then('the subscription history contains :arg1')]
    public function theSubscriptionHistoryContains(string $needle): void
    {
        $subscription = $this->reloadStoredSubscription(['historyEntries']);

        foreach ($subscription->getHistoryEntries() as $entry) {
            /** @var SubscriptionHistoryEntity $entry */
            if (str_contains((string) $entry->getComment(), $needle)) {
                return;
            }
        }

        Assert::fail(sprintf('No history entry contains "%s" for subscription %s', $needle, $subscription->getId()));
    }

    /**
     * Behat does not roll back DB state between scenarios, so previous runs leave
     * dozens (sometimes hundreds) of subscriptions behind. The detector and
     * migration handler iterate every active candidate and call the Mollie API
     * for each — which would make these scenarios run for minutes against the
     * real Mollie sandbox. Cancelling everything except the current test
     * subscription gives us deterministic isolation in a single SQL statement.
     */
    private function isolateStoredSubscription(): void
    {
        /** @var SubscriptionEntity $stored */
        $stored = Storage::get(self::STORAGE_SUBSCRIPTION);

        /** @var Connection $connection */
        $connection = $this->getContainer()->get(Connection::class);

        $connection->executeStatement(
            'UPDATE mollie_subscription SET canceled_at = NOW(3) WHERE id <> :id AND canceled_at IS NULL',
            ['id' => Uuid::fromHexToBytes($stored->getId())]
        );
    }

    private function writeSystemConfig(string $key, mixed $value): void
    {
        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . $key, $value);

        /** @var SettingsService $settingsService */
        $settingsService = $this->getContainer()->get(SettingsService::class);
        $settingsService->clearCache();
    }

    /**
     * @param list<string> $associations
     */
    private function reloadStoredSubscription(array $associations = []): SubscriptionEntity
    {
        /** @var SubscriptionEntity $stored */
        $stored = Storage::get(self::STORAGE_SUBSCRIPTION);
        $context = $this->getCurrentSalesChannelContext()->getContext();

        $criteria = new Criteria([$stored->getId()]);
        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        /** @var EntityRepository<SubscriptionCollection<SubscriptionEntity>> $repository */
        $repository = $this->getContainer()->get('mollie_subscription.repository');
        /** @var ?SubscriptionEntity $subscription */
        $subscription = $repository->search($criteria, $context)->first();
        Assert::assertNotNull($subscription, sprintf('Subscription %s not found', $stored->getId()));

        Storage::set(self::STORAGE_SUBSCRIPTION, $subscription);

        return $subscription;
    }
}
