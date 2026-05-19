<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Subscriber;

use Mollie\Shopware\Component\Payment\Event\PaymentCreatedEvent;
use Mollie\Shopware\Component\Payment\PayAction;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class SetPendingOrderSessionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentCreatedEvent::class => 'onPaymentCreated',
        ];
    }

    public function onPaymentCreated(PaymentCreatedEvent $event): void
    {
        $currentRequest = $this->requestStack->getCurrentRequest();

        if ($currentRequest === null) {
            return;
        }

        if (! str_contains($currentRequest->getPathInfo(), '/checkout/')) {
            return;
        }

        $orderId = $event->getTransactionDataStruct()->getOrder()->getId();

        $this->requestStack->getSession()->set(PayAction::SESSION_KEY_PENDING_ORDER, $orderId);
    }
}
