<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\ScheduledTask;

use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\PriceDrift\PriceDriftDetector;
use Mollie\Shopware\Component\Subscription\PriceDrift\PriceMigrationHandler;
use Mollie\Shopware\Component\Subscription\ScheduledTask\SubscriptionPriceUpdateTask;
use Mollie\Shopware\Component\Subscription\ScheduledTask\SubscriptionPriceUpdateTaskHandler;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeEntityRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGateway;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGroupCartBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * The nightly task does two independent things: notice price changes and migrate the prices that
 * the notice period has passed for. One of them failing must not stop the other, or a shop with a
 * broken sales channel would never migrate any price again.
 */
#[CoversClass(SubscriptionPriceUpdateTaskHandler::class)]
final class SubscriptionPriceUpdateTaskHandlerTest extends TestCase
{
    public function testTheTaskRunsOnItsOwnSchedule(): void
    {
        $this->assertSame([SubscriptionPriceUpdateTask::class], iterator_to_array($this->handledMessages()));
    }

    public function testBothStepsAskTheSubscriptionsForWork(): void
    {
        $subscriptionRepository = new FakeSubscriptionRepository();

        $this->handler($subscriptionRepository, new FakeSubscriptionGateway())->run();

        // One read per step; a step that never ran would not have asked.
        $this->assertCount(2, $subscriptionRepository->getSearchCriteria());
    }

    /**
     * A scheduled task that throws is retried by Shopware and floods the log. Neither step may
     * surface its failure, or one broken shop stops the whole queue.
     */
    public function testAnUnreadableSubscriptionTableDoesNotFailTheTask(): void
    {
        $subscriptionRepository = new FakeSubscriptionRepository();
        $subscriptionRepository->withSearchFailure(new \RuntimeException('database gone'));

        $this->handler($subscriptionRepository, new FakeSubscriptionGateway())->run();

        $this->assertCount(0, $subscriptionRepository->getSearchCriteria());
    }

    /**
     * @return iterable<class-string>
     */
    private function handledMessages(): iterable
    {
        return SubscriptionPriceUpdateTaskHandler::getHandledMessages();
    }

    private function handler(
        FakeSubscriptionRepository $subscriptionRepository,
        FakeSubscriptionGateway $gateway,
    ): SubscriptionPriceUpdateTaskHandler {
        $logger = new NullLogger();
        $settings = new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(
            enabled: true,
            priceUpdateMode: SubscriptionSettings::PRICE_UPDATE_MODE_AUTO,
            priceUpdateNoticeDays: 7
        ));

        $detector = new PriceDriftDetector(
            $this->salesChannelRepository(),
            $subscriptionRepository,
            $settings,
            new FakeSubscriptionGroupCartBuilder(),
            new EventSpy(),
            $logger
        );

        $migrationHandler = new PriceMigrationHandler(
            $this->salesChannelRepository(),
            $subscriptionRepository,
            $settings,
            $gateway,
            $logger
        );

        return new SubscriptionPriceUpdateTaskHandler(
            new FakeEntityRepository(new ScheduledTaskDefinition()),
            $detector,
            $migrationHandler,
            $logger
        );
    }

    private function salesChannelRepository(): FakeSalesChannelRepository
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');

        $repository = new FakeSalesChannelRepository();
        $repository->add($salesChannel);

        return $repository;
    }
}
