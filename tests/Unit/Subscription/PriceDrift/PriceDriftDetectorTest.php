<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\PriceDrift;

use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionPriceChangeNoticeEvent;
use Mollie\Shopware\Component\Subscription\PriceDrift\PriceDriftDetector;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCart;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGroupCartBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[CoversClass(PriceDriftDetector::class)]
final class PriceDriftDetectorTest extends TestCase
{
    public function testDriftDetectedTransitionsStateAndDispatchesEvent(): void
    {
        $subscription = $this->buildSubscription('subscription-id');
        $subscription->setAmount(50.00);

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $cartBuilder = new FakeSubscriptionGroupCartBuilder($this->buildGroupCart(75.00));
        $eventSpy = new EventSpy();
        $detector = $this->buildDetector(
            settings: $this->autoSettings(),
            subscriptionRepository: $repository,
            cartBuilder: $cartBuilder,
            eventDispatcher: $eventSpy
        );

        $count = $detector->detect(Context::createDefaultContext());

        $this->assertSame(1, $count);
        $this->assertSame(1, $eventSpy->getEventCount());
        $this->assertInstanceOf(SubscriptionPriceChangeNoticeEvent::class, $eventSpy->getEvent());

        $upsert = $repository->getLastUpsert();
        $this->assertSame('subscription-id', $upsert['id']);
        $this->assertSame(PriceDriftDetector::STATE_NOTIFIED, $upsert['priceUpdateState']);
        $this->assertSame(75.00, $upsert['nextNotifiedPrice']);
        $this->assertInstanceOf(\DateTimeInterface::class, $upsert['notifiedAt']);
        $this->assertNotEmpty($upsert['historyEntries']);
        $this->assertStringStartsWith('price_notified', (string) $upsert['historyEntries'][0]['comment']);
    }

    public function testEqualAmountDoesNotNotify(): void
    {
        $subscription = $this->buildSubscription('subscription-id');
        $subscription->setAmount(50.00);

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $cartBuilder = new FakeSubscriptionGroupCartBuilder($this->buildGroupCart(50.00));
        $eventSpy = new EventSpy();
        $detector = $this->buildDetector(
            settings: $this->autoSettings(),
            subscriptionRepository: $repository,
            cartBuilder: $cartBuilder,
            eventDispatcher: $eventSpy
        );

        $count = $detector->detect(Context::createDefaultContext());

        $this->assertSame(0, $count);
        $this->assertSame(0, $eventSpy->getEventCount());
        $this->assertSame(0, $repository->getUpsertCount());
    }

    public function testAlreadyNotifiedSubscriptionIsSkipped(): void
    {
        $subscription = $this->buildSubscription('subscription-id');
        $subscription->setAmount(50.00);
        $subscription->setPriceUpdateState(PriceDriftDetector::STATE_NOTIFIED);

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $cartBuilder = new FakeSubscriptionGroupCartBuilder($this->buildGroupCart(75.00));
        $eventSpy = new EventSpy();
        $detector = $this->buildDetector(
            settings: $this->autoSettings(),
            subscriptionRepository: $repository,
            cartBuilder: $cartBuilder,
            eventDispatcher: $eventSpy
        );

        $count = $detector->detect(Context::createDefaultContext());

        $this->assertSame(0, $count);
        $this->assertSame(0, $eventSpy->getEventCount());
        $this->assertSame(0, $repository->getUpsertCount());
        $this->assertSame(0, $cartBuilder->getCallCount());
    }

    public function testKeepModeSkipsAllSubscriptionsForSalesChannel(): void
    {
        $subscription = $this->buildSubscription('subscription-id');
        $subscription->setAmount(50.00);

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $cartBuilder = new FakeSubscriptionGroupCartBuilder($this->buildGroupCart(75.00));
        $eventSpy = new EventSpy();
        $detector = $this->buildDetector(
            settings: $this->keepSettings(),
            subscriptionRepository: $repository,
            cartBuilder: $cartBuilder,
            eventDispatcher: $eventSpy
        );

        $count = $detector->detect(Context::createDefaultContext());

        $this->assertSame(0, $count);
        $this->assertSame(0, $eventSpy->getEventCount());
        $this->assertSame(0, $repository->getUpsertCount());
        $this->assertSame(0, $cartBuilder->getCallCount());
    }

    public function testCartBuildExceptionLogsErrorWritesHistoryAndDispatchesNoEvent(): void
    {
        $subscription = $this->buildSubscription('subscription-id');
        $subscription->setAmount(50.00);

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $cartBuilder = new FakeSubscriptionGroupCartBuilder(null); // returns null → triggers RuntimeException
        $eventSpy = new EventSpy();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            $this->equalTo('Failed to check subscription for price drift'),
            $this->callback(function (array $context): bool {
                return ($context['subscriptionId'] ?? null) === 'subscription-id';
            })
        );
        $logger->method('info');
        $logger->method('debug');

        $detector = $this->buildDetector(
            settings: $this->autoSettings(),
            subscriptionRepository: $repository,
            cartBuilder: $cartBuilder,
            eventDispatcher: $eventSpy,
            logger: $logger
        );

        $count = $detector->detect(Context::createDefaultContext());

        $this->assertSame(0, $count);
        $this->assertSame(0, $eventSpy->getEventCount());
        $this->assertSame(1, $repository->getUpsertCount());

        $upsert = $repository->getLastUpsert();
        $this->assertArrayNotHasKey('priceUpdateState', $upsert);
        $this->assertNotEmpty($upsert['historyEntries']);
        $this->assertStringStartsWith('price_check_skipped', (string) $upsert['historyEntries'][0]['comment']);
    }

    public function testCanceledSubscriptionIsSkipped(): void
    {
        $subscription = $this->buildSubscription('subscription-id');
        $subscription->setAmount(50.00);
        $subscription->setCanceledAt(new \DateTime());

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $cartBuilder = new FakeSubscriptionGroupCartBuilder($this->buildGroupCart(75.00));
        $eventSpy = new EventSpy();
        $detector = $this->buildDetector(
            settings: $this->autoSettings(),
            subscriptionRepository: $repository,
            cartBuilder: $cartBuilder,
            eventDispatcher: $eventSpy
        );

        $count = $detector->detect(Context::createDefaultContext());

        $this->assertSame(0, $count);
        $this->assertSame(0, $eventSpy->getEventCount());
        $this->assertSame(0, $cartBuilder->getCallCount());
    }

    private function buildDetector(
        SubscriptionSettings $settings,
        FakeSubscriptionRepository $subscriptionRepository,
        FakeSubscriptionGroupCartBuilder $cartBuilder,
        EventSpy $eventDispatcher,
        ?LoggerInterface $logger = null
    ): PriceDriftDetector {
        return new PriceDriftDetector(
            $this->buildSalesChannelRepository(),
            $subscriptionRepository,
            $this->buildCustomerRepository($this->buildCustomer()),
            new FakeSettingsService(subscriptionSettings: $settings),
            $cartBuilder,
            $eventDispatcher,
            $logger ?? new NullLogger()
        );
    }

    private function buildSubscription(string $id): SubscriptionEntity
    {
        return SubscriptionEntityBuilder::create()
            ->withId($id)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->build()
        ;
    }

    private function buildGroupCart(float $gross): SubscriptionGroupCart
    {
        $cart = new Cart('test-token');
        $cart->setPrice(new CartPrice(
            $gross * 0.84,
            $gross,
            $gross,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS
        ));

        return new SubscriptionGroupCart($cart, new FakeSalesChannelContext());
    }

    private function autoSettings(): SubscriptionSettings
    {
        return new SubscriptionSettings(
            enabled: true,
            priceUpdateMode: SubscriptionSettings::PRICE_UPDATE_MODE_AUTO,
            priceUpdateNoticeDays: 7
        );
    }

    private function keepSettings(): SubscriptionSettings
    {
        return new SubscriptionSettings(
            enabled: true,
            priceUpdateMode: SubscriptionSettings::PRICE_UPDATE_MODE_KEEP
        );
    }

    /**
     * @return EntityRepository<SalesChannelCollection>
     */
    private function buildSalesChannelRepository(): EntityRepository
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');
        $salesChannel->setUniqueIdentifier('sales-channel-id');
        $salesChannel->setName('Storefront');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            function (Criteria $criteria, Context $context) use ($salesChannel): EntitySearchResult {
                $collection = new SalesChannelCollection([$salesChannel]);

                return new EntitySearchResult(SalesChannelEntity::class, $collection->count(), $collection, null, $criteria, $context);
            }
        );

        return $repository;
    }

    /**
     * @return EntityRepository<CustomerCollection<CustomerEntity>>
     */
    private function buildCustomerRepository(CustomerEntity $customer): EntityRepository
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            function (Criteria $criteria, Context $context) use ($customer): EntitySearchResult {
                $collection = new CustomerCollection([$customer]);

                return new EntitySearchResult(CustomerEntity::class, $collection->count(), $collection, null, $criteria, $context);
            }
        );

        return $repository;
    }

    private function buildCustomer(): CustomerEntity
    {
        return CustomerBuilder::create()
            ->withEmail('test@example.com')
            ->withFirstName('Jane')
            ->withLastName('Doe')
            ->build()
        ;
    }
}
