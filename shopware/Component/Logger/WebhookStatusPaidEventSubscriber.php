<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Mollie\Shopware\Component\FlowBuilder\Event\Payment\SuccessEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPaidEvent;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

final class WebhookStatusPaidEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(value: '%kernel.logs_dir%')]
        private string $logDir,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WebhookStatusPaidEvent::class => 'onWebhookStatusPaid',
            SuccessEvent::class => 'onWebhookStatusPaid',
        ];
    }

    /**
     * @param SuccessEvent|WebhookStatusPaidEvent $event
     */
    public function onWebhookStatusPaid(Event $event): void
    {
        try {
            $payment = $event->getPayment();
            $successStatus = [
                PaymentStatus::PAID,
                PaymentStatus::AUTHORIZED
            ];
            if (! in_array($payment->getStatus(),$successStatus)) {
                return;
            }
            $orderNumber = (string) $event->getOrder()->getOrderNumber();

            $logFile = $this->logDir . '/mollie/order-' . $orderNumber . '.log';

            if (file_exists($logFile)) {
                unlink($logFile);
                $this->logger->info('Order log file deleted on paid webhook', ['order_number' => $orderNumber]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error deleting order log file: ' . $e->getMessage());
        }
    }
}
