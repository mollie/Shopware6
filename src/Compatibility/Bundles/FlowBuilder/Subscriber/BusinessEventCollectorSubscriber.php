<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Subscriber;


use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund\RefundStartedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookReceivedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedAuthorizedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedCancelledEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedChargebackEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedCompletedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedExpiredEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedFailedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedPaidEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedPartialRefundedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedPendingEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedRefundedEvent;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class BusinessEventCollectorSubscriber implements EventSubscriberInterface
{

    /**
     * @var BusinessEventCollector
     */
    private $businessEventCollector;


    /**
     * @param BusinessEventCollector $businessEventCollector
     */
    public function __construct(BusinessEventCollector $businessEventCollector)
    {
        $this->businessEventCollector = $businessEventCollector;
    }

    /**
     * @return array[]
     */
    public static function getSubscribedEvents()
    {
        return [
            BusinessEventCollectorEvent::NAME => ['onAddEvent', 1000],
        ];
    }

    /**
     * @param BusinessEventCollectorEvent $event
     * @return void
     */
    public function onAddEvent(BusinessEventCollectorEvent $event): void
    {
        $collection = $event->getCollection();

        /** @var BusinessEventDefinition $definition */
        $definition = $this->businessEventCollector->define(WebhookReceivedEvent::class);
        $collection->set($definition->getName(), $definition);

        /** @var BusinessEventDefinition $definition */
        $definition = $this->businessEventCollector->define(WebhookReceivedPaidEvent::class);
        $collection->set($definition->getName(), $definition);

        /** @var BusinessEventDefinition $definition */
        $definition = $this->businessEventCollector->define(WebhookReceivedFailedEvent::class);
        $collection->set($definition->getName(), $definition);

        /** @var BusinessEventDefinition $definition */
        $definition = $this->businessEventCollector->define(WebhookReceivedExpiredEvent::class);
        $collection->set($definition->getName(), $definition);

        /** @var BusinessEventDefinition $definition */
        $definition = $this->businessEventCollector->define(WebhookReceivedCancelledEvent::class);
        $collection->set($definition->getName(), $definition);

        /** @var BusinessEventDefinition $definition */
        $definition = $this->businessEventCollector->define(WebhookReceivedPendingEvent::class);
        $collection->set($definition->getName(), $definition);

        /** @var BusinessEventDefinition $definition */
        $definition = $this->businessEventCollector->define(WebhookReceivedCompletedEvent::class);
        $collection->set($definition->getName(), $definition);

        /** @var BusinessEventDefinition $definition */
        $definition = $this->businessEventCollector->define(WebhookReceivedAuthorizedEvent::class);
        $collection->set($definition->getName(), $definition);

        /** @var BusinessEventDefinition $definition */
        $definition = $this->businessEventCollector->define(WebhookReceivedChargebackEvent::class);
        $collection->set($definition->getName(), $definition);

        /** @var BusinessEventDefinition $definition */
        $definition = $this->businessEventCollector->define(RefundStartedEvent::class);
        $collection->set($definition->getName(), $definition);

        /** @var BusinessEventDefinition $definition */
        $definition = $this->businessEventCollector->define(WebhookReceivedRefundedEvent::class);
        $collection->set($definition->getName(), $definition);

        /** @var BusinessEventDefinition $definition */
        $definition = $this->businessEventCollector->define(WebhookReceivedPartialRefundedEvent::class);
        $collection->set($definition->getName(), $definition);

    }

}
