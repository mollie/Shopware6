<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\ScheduledTask;

use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\PriceDrift\PriceDriftDetector;
use Mollie\Shopware\Component\Subscription\ScheduledTask\SubscriptionPriceUpdateTask;
use Mollie\Shopware\Component\Subscription\ScheduledTask\SubscriptionPriceUpdateTaskHandler;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGroupCartBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

#[CoversClass(SubscriptionPriceUpdateTask::class)]
#[CoversClass(SubscriptionPriceUpdateTaskHandler::class)]
final class SubscriptionPriceUpdateTaskHandlerTest extends TestCase
{
    public function testTaskNameAndIntervalMatchExpectedDefaults(): void
    {
        $this->assertSame('mollie.subscriptions.price_update', SubscriptionPriceUpdateTask::getTaskName());
        $this->assertSame(86400, SubscriptionPriceUpdateTask::getDefaultInterval());
    }

    public function testHandlerDelegatesToDetectorAndLogsResultCount(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('0 subscription price change notices dispatched'))
        ;
        $logger->method('info');
        $logger->method('error');

        $handler = new SubscriptionPriceUpdateTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->buildDetectorWithEmptyChannels($logger),
            $logger
        );

        $handler->run();
    }

    public function testHandlerSwallowsDetectorExceptionsAndLogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Subscription price update scheduled task (detect) failed: boom'))
        ;
        $logger->method('info');
        $logger->method('debug');

        $throwingSalesChannelRepository = $this->createMock(EntityRepository::class);
        $throwingSalesChannelRepository->method('search')->willThrowException(new \RuntimeException('boom'));

        $detector = new PriceDriftDetector(
            $throwingSalesChannelRepository,
            new FakeSubscriptionRepository(),
            $this->createMock(EntityRepository::class),
            new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: true)),
            new FakeSubscriptionGroupCartBuilder(),
            new EventSpy(),
            $logger
        );

        $handler = new SubscriptionPriceUpdateTaskHandler(
            $this->createMock(EntityRepository::class),
            $detector,
            $logger
        );

        $handler->run();
    }

    public function testGetHandledMessagesReturnsTaskClass(): void
    {
        $messages = iterator_to_array(SubscriptionPriceUpdateTaskHandler::getHandledMessages());

        $this->assertSame([SubscriptionPriceUpdateTask::class], $messages);
    }

    private function buildDetectorWithEmptyChannels(LoggerInterface $logger): PriceDriftDetector
    {
        $emptySalesChannelRepository = $this->createMock(EntityRepository::class);
        $emptySalesChannelRepository->method('search')->willReturnCallback(
            function (Criteria $criteria, Context $context): EntitySearchResult {
                $collection = new SalesChannelCollection();

                return new EntitySearchResult(SalesChannelCollection::class, 0, $collection, null, $criteria, $context);
            }
        );

        $emptyCustomerRepository = $this->createMock(EntityRepository::class);
        $emptyCustomerRepository->method('search')->willReturnCallback(
            function (Criteria $criteria, Context $context): EntitySearchResult {
                $collection = new CustomerCollection();

                return new EntitySearchResult(CustomerCollection::class, 0, $collection, null, $criteria, $context);
            }
        );

        return new PriceDriftDetector(
            $emptySalesChannelRepository,
            new FakeSubscriptionRepository(),
            $emptyCustomerRepository,
            new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: true)),
            new FakeSubscriptionGroupCartBuilder(),
            new EventSpy(),
            $logger
        );
    }
}
