<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Subscriber;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund\RefundStartedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionCancelledEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionEndedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionRemindedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionRenewedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionStartedEvent;
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
     * @return array<mixed>
     */
    public static function getSubscribedEvents()
    {
        # older Shopware versions do not work here.
        # Problem is that we cannot compare Shopware versions because
        # we are in a static class and do not yet have dependency data
        if (!class_exists("\Shopware\Core\Framework\Event\BusinessEventCollectorEvent")) {
            return [];
        }

        return [
            \Shopware\Core\Framework\Event\BusinessEventCollectorEvent::NAME => ['onAddEvent', 1000],
        ];
    }

    /**
     * @param \Shopware\Core\Framework\Event\BusinessEventCollectorEvent $event
     * @return void
     */
    public function onAddEvent(\Shopware\Core\Framework\Event\BusinessEventCollectorEvent $event): void
    {
        $collection = $event->getCollection();

        $events = [
            WebhookReceivedEvent::class,
            # --------------------------------------------
            WebhookReceivedPaidEvent::class,
            WebhookReceivedFailedEvent::class,
            WebhookReceivedExpiredEvent::class,
            WebhookReceivedCancelledEvent::class,
            WebhookReceivedPendingEvent::class,
            WebhookReceivedCompletedEvent::class,
            WebhookReceivedAuthorizedEvent::class,
            WebhookReceivedChargebackEvent::class,
            WebhookReceivedRefundedEvent::class,
            WebhookReceivedPartialRefundedEvent::class,
            # --------------------------------------------
            RefundStartedEvent::class,
            # --------------------------------------------
            SubscriptionStartedEvent::class,
            SubscriptionEndedEvent::class,
            SubscriptionCancelledEvent::class,
            SubscriptionRemindedEvent::class,
            SubscriptionRenewedEvent::class,
        ];

        foreach ($events as $event) {
            /** @var BusinessEventDefinition $definition */
            $definition = $this->businessEventCollector->define($event);
            $collection->set($definition->getName(), $definition);
        }
    }
}
