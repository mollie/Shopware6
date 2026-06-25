<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.scheduled_task')]
final class SubscriptionPriceUpdateTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'mollie.subscriptions.price_update';
    }

    public static function getDefaultInterval(): int
    {
        return 300; // every 5 minutes — drains the dirty/notified backlog quickly; idle runs are cheap
    }
}
