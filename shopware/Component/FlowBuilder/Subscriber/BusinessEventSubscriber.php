<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Subscriber;

use Mollie\Shopware\Component\FlowBuilder\Event\Payment\CancelledEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Payment\FailedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Payment\SuccessEvent;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class BusinessEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var class-string[]
     */
    private array $flowEventList = [
        SuccessEvent::class,
        FailedEvent::class,
        CancelledEvent::class,
    ];

    public function __construct(private BusinessEventCollector $businessEventCollector)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BusinessEventCollectorEvent::NAME => ['addEvents', 1000],
        ];
    }

    public function addEvents(BusinessEventCollectorEvent $eventCollectorEvent): void
    {
        $collection = $eventCollectorEvent->getCollection();
        $flowEventList = $this->flowEventList;
        $flowEventList = array_merge($flowEventList, PaymentStatus::getAllWebhookEvents());

        foreach ($flowEventList as $className) {
            $definition = $this->businessEventCollector->define($className);
            if (! $definition) {
                continue;
            }
            $collection->set($definition->getName(), $definition);
        }
    }
}
