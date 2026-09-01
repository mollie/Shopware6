<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Subscriber;

use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPaidEvent;
use Mollie\Shopware\Component\Payment\DuplicatePaymentReconciler;
use Mollie\Shopware\Component\Payment\DuplicatePaymentReconcilerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class DuplicatePaymentSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = 0;

    public function __construct(
        #[Autowire(service: DuplicatePaymentReconciler::class)]
        private readonly DuplicatePaymentReconcilerInterface $reconciler,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WebhookStatusPaidEvent::class => ['onPaidWebhook', self::PRIORITY],
        ];
    }

    public function onPaidWebhook(WebhookStatusPaidEvent $event): void
    {
        $currentTransactionId = $event->getPayment()->getShopwareTransaction()->getId();
        $this->reconciler->reconcile($event->getOrder(), $currentTransactionId, $event->getContext());
    }
}
