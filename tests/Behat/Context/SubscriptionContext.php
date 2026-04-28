<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Step\Then;
use Behat\Step\When;
use Mollie\Shopware\Behat\Storage;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Route\WebhookRoute;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandler;
use Mollie\Shopware\Integration\Data\CustomerTestBehaviour;
use Mollie\Shopware\Integration\Data\SubscriptionTestBehaviour;
use PHPUnit\Framework\Assert;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\Request;

final class SubscriptionContext extends ShopwareContext
{
    use SubscriptionTestBehaviour;
    use CustomerTestBehaviour;
    public const STORAGE_SUBSCRIPTION = 'subscription';
    public const STORAGE_REMEMBERED_SUBSCRIPTION_ID = 'rememberedSubscriptionId';

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
}
