<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Subscriber;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderCanceled\OrderCanceledEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderCanceled\OrderCanceledEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderFailed\OrderFailedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderFailed\OrderFailedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderSuccess\OrderSuccessEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderSuccess\OrderSuccessEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund\RefundStarted\RefundStartedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund\RefundStarted\RefundStartedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionCancelled\SubscriptionCancelledEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionCancelled\SubscriptionCancelledEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionEnded\SubscriptionEndedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionEnded\SubscriptionEndedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionPaused\SubscriptionPausedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionPaused\SubscriptionPausedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionReminded\SubscriptionRemindedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionReminded\SubscriptionRemindedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionRenewed\SubscriptionRenewedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionRenewed\SubscriptionRenewedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionResumed\SubscriptionResumedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionResumed\SubscriptionResumedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionSkipped\SubscriptionSkippedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionSkipped\SubscriptionSkippedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionStarted\SubscriptionStartedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionStarted\SubscriptionStartedEvent651;
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
use Kiener\MolliePayments\Compatibility\VersionCompare;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Shopware\Core\Framework\Event\BusinessEventDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BusinessEventCollectorSubscriber implements EventSubscriberInterface
{
    /**
     * @var VersionCompare
     */
    private $versionCompare;

    /**
     * @var BusinessEventCollector
     */
    private $businessEventCollector;


    /**
     * @param string $shopwareVersion
     * @param BusinessEventCollector $businessEventCollector
     */
    public function __construct(string $shopwareVersion, BusinessEventCollector $businessEventCollector)
    {
        $this->versionCompare = new VersionCompare($shopwareVersion);

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
        ];

        if ($this->versionCompare->gte('6.5.1.0')) {
            $events[] = OrderSuccessEvent651::class;
            $events[] = OrderFailedEvent651::class;
            $events[] = OrderCanceledEvent651::class;
            # --------------------------------------------
            $events[] = RefundStartedEvent651::class;
            # --------------------------------------------
            $events[] = SubscriptionRemindedEvent651::class;
            $events[] = SubscriptionStartedEvent651::class;
            $events[] = SubscriptionPausedEvent651::class;
            $events[] = SubscriptionEndedEvent651::class;
            $events[] = SubscriptionResumedEvent651::class;
            $events[] = SubscriptionSkippedEvent651::class;
            $events[] = SubscriptionCancelledEvent651::class;
            $events[] = SubscriptionRenewedEvent651::class;
        } else {
            $events[] = OrderSuccessEvent::class;
            $events[] = OrderFailedEvent::class;
            $events[] = OrderCanceledEvent::class;
            # --------------------------------------------
            $events[] = RefundStartedEvent::class;
            # --------------------------------------------
            $events[] = SubscriptionRemindedEvent::class;
            $events[] = SubscriptionStartedEvent::class;
            $events[] = SubscriptionPausedEvent::class;
            $events[] = SubscriptionEndedEvent::class;
            $events[] = SubscriptionResumedEvent::class;
            $events[] = SubscriptionSkippedEvent::class;
            $events[] = SubscriptionCancelledEvent::class;
            $events[] = SubscriptionRenewedEvent::class;
        }

        foreach ($events as $tmpEvent) {
            /** @var BusinessEventDefinition $definition */
            $definition = $this->businessEventCollector->define($tmpEvent);
            $collection->set($definition->getName(), $definition);
        }
    }
}
