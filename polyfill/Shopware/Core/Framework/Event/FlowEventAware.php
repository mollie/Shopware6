<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

if (interface_exists(FlowEventAware::class)) {
    return;
}

use Shopware\Core\Framework\Event\EventData\EventDataCollection;

interface FlowEventAware extends ShopwareEvent
{
    public static function getAvailableData(): EventDataCollection;

    public function getName(): string;
}
