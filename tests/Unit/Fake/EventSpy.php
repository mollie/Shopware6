<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Psr\EventDispatcher\EventDispatcherInterface;

final class EventSpy implements EventDispatcherInterface
{
    private object $event;

    public function dispatch(object $event)
    {
        $this->event = $event;
    }

    public function getEvent(): object
    {
        return $this->event;
    }
}
