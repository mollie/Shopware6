<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\FlowEventAware;
use Symfony\Contracts\EventDispatcher\Event;

final class FakeFlowEventAware extends Event implements FlowEventAware
{
    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return 'fake.flow.event';
    }

    public function getContext(): Context
    {
        return new Context(new SystemSource());
    }
}
