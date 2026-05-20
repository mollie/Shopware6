<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Event;

final class SubscriptionPriceChangeNoticeEvent extends SubscriptionActionEvent
{
    protected function getEventName(): string
    {
        return 'priceChangeNotice';
    }
}
