<?php

namespace MolliePayments\Tests\Fakes;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FakeEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var object
     */
    private $dispatchedEvent;

    /**
     * @var string
     */
    private $dispatchedEventName;


    /**
     * @return object
     */
    public function getDispatchedEvent(): object
    {
        return $this->dispatchedEvent;
    }

    /**
     * @return string
     */
    public function getDispatchedEventName(): string
    {
        return $this->dispatchedEventName;
    }


    /**
     * @param object $event
     * @param null|string $eventName
     * @return object
     */
    public function dispatch(object $event, string $eventName = null): object
    {
        $this->dispatchedEvent = $event;
        $this->dispatchedEventName = $eventName;

        return $event;
    }

    public function addListener(string $eventName, callable $listener, int $priority = 0)
    {
        // TODO: Implement addListener() method.
    }

    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        // TODO: Implement addSubscriber() method.
    }

    public function removeListener(string $eventName, callable $listener)
    {
        // TODO: Implement removeListener() method.
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber)
    {
        // TODO: Implement removeSubscriber() method.
    }

    public function getListeners(string $eventName = null): array
    {
        // TODO: Implement getListeners() method.
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        // TODO: Implement getListenerPriority() method.
    }

    public function hasListeners(string $eventName = null): bool
    {
        // TODO: Implement hasListeners() method.
    }


}
