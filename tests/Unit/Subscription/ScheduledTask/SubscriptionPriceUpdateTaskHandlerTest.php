<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\ScheduledTask;

use Mollie\Shopware\Component\Subscription\ScheduledTask\SubscriptionPriceUpdateTask;
use Mollie\Shopware\Component\Subscription\ScheduledTask\SubscriptionPriceUpdateTaskHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionPriceUpdateTask::class)]
#[CoversClass(SubscriptionPriceUpdateTaskHandler::class)]
final class SubscriptionPriceUpdateTaskHandlerTest extends TestCase
{
    public function testTaskNameAndIntervalMatchExpectedDefaults(): void
    {
        $this->assertSame('mollie.subscriptions.price_update', SubscriptionPriceUpdateTask::getTaskName());
        $this->assertSame(300, SubscriptionPriceUpdateTask::getDefaultInterval());
    }

    public function testGetHandledMessagesReturnsTaskClass(): void
    {
        $messages = iterator_to_array(SubscriptionPriceUpdateTaskHandler::getHandledMessages());

        $this->assertSame([SubscriptionPriceUpdateTask::class], $messages);
    }
}
