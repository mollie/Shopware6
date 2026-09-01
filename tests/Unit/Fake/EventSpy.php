<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class EventSpy implements EventDispatcherInterface
{
    /** @var list<object> */
    private array $events = [];

    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->events[] = $event;

        $name = $eventName ?? $event::class;

        foreach ($this->listeners[$name] ?? [] as $listener) {
            $listener($event, $name, $this);
        }

        return $event;
    }

    public function getEvent(): object
    {
        if ($this->events === []) {
            throw new \RuntimeException('EventSpy has no events recorded.');
        }

        return $this->events[array_key_last($this->events)];
    }

    /**
     * @return list<object>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function getEventCount(): int
    {
        return count($this->events);
    }

    /**
     * Symfony dispatches an event without an explicit name under its class name, so a listener
     * registered for the event class is what a production subscriber effectively is.
     */
    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
    }

    public function removeListener(string $eventName, callable $listener): void
    {
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
    }

    public function getListeners(?string $eventName = null): array
    {
        if ($eventName === null) {
            return $this->listeners;
        }

        return $this->listeners[$eventName] ?? [];
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        return null;
    }

    public function hasListeners(?string $eventName = null): bool
    {
        if ($eventName === null) {
            return count($this->listeners) > 0;
        }

        return count($this->listeners[$eventName] ?? []) > 0;
    }
}
