<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Component\Payment\Event\PaymentFinalizeEvent;
use Mollie\Shopware\Component\Payment\Route\AbstractWebhookRoute;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Shipment\OrderShippedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * We do not want to change the payment status in production,
 * because there might be a race condition error where the status is changed
 * by the code during redirect back to shop and at same time over webhook.
 * In dev environment the webhook URL is not reachable from outside, therefore we want to change the status
 */
final class DevWebHookSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: WebhookRoute::class)]
        private AbstractWebhookRoute $webhookRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentFinalizeEvent::class => 'handleFinalizeEvent',
            OrderShippedEvent::class => 'onOrderShipped',
        ];
    }

    public function handleFinalizeEvent(PaymentFinalizeEvent $event): void
    {
        $environmentSettings = $this->settingsService->getEnvironmentSettings();
        if (! $environmentSettings->isDevMode() && ! $environmentSettings->isCypressMode()) {
            return;
        }
        $this->logger->warning('Executing Webhook in Dev mode');
        $payment = $event->getPayment();
        $transaction = $payment->getShopwareTransaction();
        $this->webhookRoute->notify($transaction->getId(), $event->getContext());
    }

    public function onOrderShipped(OrderShippedEvent $event): void
    {
        $environmentSettings = $this->settingsService->getEnvironmentSettings();

        if (! $environmentSettings->isDevMode() && ! $environmentSettings->isCypressMode()) {
            return;
        }
        $this->logger->warning('Executing Webhook in Dev mode');
        sleep(2);
        $this->webhookRoute->notify($event->getTransactionId(), $event->getContext());
    }
}
