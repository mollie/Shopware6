<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Component\Payment\Event\PaymentFinalizeEvent;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * We do not want to change the payment status in production,
 * because there might be a race condition error where the status is changed
 * by the code during redirect back to shop and at same time over webhook.
 * In dev environment the webhook URL is not reachable from outside, therefore we want to change the status
 */
final class PaymentFinalizeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AbstractSettingsService $settingsService,
        private WebhookRoute $webhookRoute,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentFinalizeEvent::class => 'handleFinalizeEvent',
        ];
    }

    public function handleFinalizeEvent(PaymentFinalizeEvent $event): void
    {
        $environmentSettings = $this->settingsService->getEnvironmentSettings();

        if (! $environmentSettings->isDevMode() && ! $environmentSettings->isCypressMode()) {
            return;
        }
        $payment = $event->getPayment();
        $transaction = $payment->getShopwareTransaction();
        $this->webhookRoute->notify($transaction->getId(), $event->getContext());
    }
}
