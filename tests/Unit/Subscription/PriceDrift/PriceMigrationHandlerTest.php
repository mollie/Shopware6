<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\PriceDrift;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Subscription as MollieSubscription;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\PriceDrift\PriceDriftDetector;
use Mollie\Shopware\Component\Subscription\PriceDrift\PriceMigrationHandler;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGateway;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[CoversClass(PriceMigrationHandler::class)]
final class PriceMigrationHandlerTest extends TestCase
{
    public function testNoticeWindowNotElapsedSkipsMigration(): void
    {
        $subscription = $this->buildNotifiedSubscription(notifiedDaysAgo: 2, nextNotifiedPrice: 75.00);
        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $gateway = new FakeSubscriptionGateway();
        $gateway->register($this->buildMollieSubscription('sub_test123', 50.00));

        $handler = $this->buildHandler(
            settings: $this->autoSettings(noticeDays: 7),
            subscriptionRepository: $repository,
            gateway: $gateway
        );

        $count = $handler->migrate(Context::createDefaultContext());

        $this->assertSame(0, $count);
        $this->assertSame(0, $gateway->getCallCount('updateSubscription'));
        $this->assertSame(0, $repository->getUpsertCount());
    }

    public function testUpdateApiSuccessClearsStateAndUpdatesAmount(): void
    {
        $subscription = $this->buildNotifiedSubscription(notifiedDaysAgo: 10, nextNotifiedPrice: 75.00);
        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $gateway = new FakeSubscriptionGateway();
        $gateway->register($this->buildMollieSubscription('sub_test123', 50.00));

        $handler = $this->buildHandler(
            settings: $this->autoSettings(noticeDays: 7),
            subscriptionRepository: $repository,
            gateway: $gateway
        );

        $count = $handler->migrate(Context::createDefaultContext());

        $this->assertSame(1, $count);
        $this->assertSame(1, $gateway->getCallCount('updateSubscription'));
        $this->assertSame(0, $gateway->getCallCount('cancelSubscription'));
        $this->assertSame(0, $gateway->getCallCount('createSubscription'));

        $upsert = $repository->getLastUpsert();
        $this->assertSame('subscription-id', $upsert['id']);
        $this->assertSame(75.00, $upsert['amount']);
        $this->assertSame(PriceDriftDetector::STATE_NONE, $upsert['priceUpdateState']);
        $this->assertNull($upsert['notifiedAt']);
        $this->assertNull($upsert['nextNotifiedPrice']);
        $this->assertStringStartsWith('price_migrated:', (string) $upsert['historyEntries'][0]['comment']);
    }

    public function testUpdateApiClientExceptionLeavesStateAtNotifiedAndWritesFailureHistory(): void
    {
        $subscription = $this->buildNotifiedSubscription(notifiedDaysAgo: 10, nextNotifiedPrice: 99.00);
        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $gateway = new FakeSubscriptionGateway();
        $gateway->register($this->buildMollieSubscription('sub_test123', 50.00));
        $gateway->throwOnUpdate($this->buildClientException());

        $handler = $this->buildHandler(
            settings: $this->autoSettings(noticeDays: 7),
            subscriptionRepository: $repository,
            gateway: $gateway
        );

        $count = $handler->migrate(Context::createDefaultContext());

        $this->assertSame(0, $count);
        $this->assertSame(0, $gateway->getCallCount('cancelSubscription'));
        $this->assertSame(0, $gateway->getCallCount('createSubscription'));

        $upsert = $repository->getLastUpsert();
        $this->assertArrayNotHasKey('priceUpdateState', $upsert);
        $this->assertArrayNotHasKey('amount', $upsert);
        $this->assertStringStartsWith('price_migration_failed:', (string) $upsert['historyEntries'][0]['comment']);
    }

    public function testMissingMollieSubscriptionInBulkLoadIsTreatedAsFailure(): void
    {
        $subscription = $this->buildNotifiedSubscription(notifiedDaysAgo: 10, nextNotifiedPrice: 75.00);
        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $gateway = new FakeSubscriptionGateway();
        // intentionally no register() — listSubscriptions returns empty, candidate isn't found

        $handler = $this->buildHandler(
            settings: $this->autoSettings(noticeDays: 7),
            subscriptionRepository: $repository,
            gateway: $gateway
        );

        $count = $handler->migrate(Context::createDefaultContext());

        $this->assertSame(0, $count);
        $this->assertSame(0, $gateway->getCallCount('updateSubscription'));
        $upsert = $repository->getLastUpsert();
        $this->assertArrayNotHasKey('priceUpdateState', $upsert);
        $this->assertArrayNotHasKey('amount', $upsert);
        $this->assertStringStartsWith('price_migration_failed:', (string) $upsert['historyEntries'][0]['comment']);
    }

    public function testListSubscriptionsExceptionIsSwallowedAndAllCandidatesFail(): void
    {
        $subscription = $this->buildNotifiedSubscription(notifiedDaysAgo: 10, nextNotifiedPrice: 75.00);
        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $gateway = new FakeSubscriptionGateway();
        $gateway->register($this->buildMollieSubscription('sub_test123', 50.00));
        $gateway->throwOnList(new \RuntimeException('mollie list endpoint down'));

        $handler = $this->buildHandler(
            settings: $this->autoSettings(noticeDays: 7),
            subscriptionRepository: $repository,
            gateway: $gateway
        );

        $count = $handler->migrate(Context::createDefaultContext());

        $this->assertSame(0, $count);
        $this->assertSame(0, $gateway->getCallCount('updateSubscription'));
        $upsert = $repository->getLastUpsert();
        $this->assertStringStartsWith('price_migration_failed:', (string) $upsert['historyEntries'][0]['comment']);
    }

    public function testKeepModeSkipsMigration(): void
    {
        $subscription = $this->buildNotifiedSubscription(notifiedDaysAgo: 100, nextNotifiedPrice: 75.00);
        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $gateway = new FakeSubscriptionGateway();
        $gateway->register($this->buildMollieSubscription('sub_test123', 50.00));

        $handler = $this->buildHandler(
            settings: $this->keepSettings(),
            subscriptionRepository: $repository,
            gateway: $gateway
        );

        $count = $handler->migrate(Context::createDefaultContext());

        $this->assertSame(0, $count);
        $this->assertSame(0, $gateway->getCallCount('updateSubscription'));
        $this->assertSame(0, $repository->getUpsertCount());
    }

    private function buildHandler(
        SubscriptionSettings $settings,
        FakeSubscriptionRepository $subscriptionRepository,
        FakeSubscriptionGateway $gateway
    ): PriceMigrationHandler {
        return new PriceMigrationHandler(
            $this->buildSalesChannelRepository(),
            $subscriptionRepository,
            new FakeSettingsService(subscriptionSettings: $settings),
            $gateway,
            new NullLogger()
        );
    }

    private function buildNotifiedSubscription(int $notifiedDaysAgo, float $nextNotifiedPrice): SubscriptionEntity
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withMollieId('sub_test123')
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->build()
        ;
        $subscription->setAmount(50.00);
        $subscription->setPriceUpdateState(PriceDriftDetector::STATE_NOTIFIED);
        $subscription->setNextNotifiedPrice($nextNotifiedPrice);
        $subscription->setNotifiedAt((new \DateTimeImmutable())->modify('-' . $notifiedDaysAgo . ' day'));

        return $subscription;
    }

    private function buildMollieSubscription(string $id, float $amount): MollieSubscription
    {
        $subscription = new MollieSubscription(
            $id,
            'cst_test123',
            'mdt_test',
            SubscriptionStatus::ACTIVE,
            new Interval(1, IntervalUnit::MONTHS),
            new Money($amount, 'EUR'),
            'Test subscription',
            'https://example.com/webhook',
            ['original_order_number' => '10000'],
            new \DateTimeImmutable('2026-01-01')
        );
        $subscription->setNextPaymentDate(new \DateTimeImmutable('+30 days'));

        return $subscription;
    }

    private function buildClientException(): ClientException
    {
        $response = new Response(status: 422, body: json_encode(['title' => 'Invalid amount']));

        return new ClientException('PATCH rejected', new Request('PATCH', '/subscription'), $response);
    }

    private function autoSettings(int $noticeDays): SubscriptionSettings
    {
        return new SubscriptionSettings(
            enabled: true,
            priceUpdateMode: SubscriptionSettings::PRICE_UPDATE_MODE_AUTO,
            priceUpdateNoticeDays: $noticeDays
        );
    }

    private function keepSettings(): SubscriptionSettings
    {
        return new SubscriptionSettings(
            enabled: true,
            priceUpdateMode: SubscriptionSettings::PRICE_UPDATE_MODE_KEEP
        );
    }

    private function buildSalesChannelRepository(): FakeSalesChannelRepository
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');
        $salesChannel->setUniqueIdentifier('sales-channel-id');
        $salesChannel->setName('Storefront');

        $repository = new FakeSalesChannelRepository();
        $repository->add($salesChannel);

        return $repository;
    }
}
