<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Subscriber;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderCanceledEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderFailedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderSuccessEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund\RefundStartedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionCancelledEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionEndedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionPausedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionRemindedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionRenewedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionResumedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionSkippedEvent;
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
     * @return array<mixed>
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

        $events = [
            OrderSuccessEvent::class,
            OrderFailedEvent::class,
            OrderCanceledEvent::class,
            # --------------------------------------------
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
            SubscriptionPausedEvent::class,
            SubscriptionResumedEvent::class,
            SubscriptionSkippedEvent::class,
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
