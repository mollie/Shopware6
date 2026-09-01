<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction\Subscriber;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Transaction\Event\RepairLegacyTransactionEvent;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class RepairLegacyTransactionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RepairLegacyTransactionEvent::class => 'onRepairLegacyTransaction',
        ];
    }

    public function onRepairLegacyTransaction(RepairLegacyTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();

        // The transaction already carries Mollie data (built by OrderTransactionSubscriber from its custom
        // fields), so there is nothing to repair and no need to call Mollie.
        if ($transaction->getExtension(Mollie::EXTENSION) instanceof Payment) {
            return;
        }

        $order = $event->getOrder();

        try {
            $payment = $this->mollieGateway->repairLegacyTransaction($transaction, $order, $event->getContext());
        } catch (\Throwable $exception) {
            // A failed repair (e.g. Mollie API not reachable) must not interrupt the ship/refund/cancel
            // flow. The transaction simply stays without the extension and the caller handles that.
            $this->logger->error('Failed to repair legacy transaction from order', [
                'transactionId' => $transaction->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if (! $payment instanceof Payment) {
            return;
        }

        $payment->setShopwareTransaction($transaction);
        $transaction->addExtension(Mollie::EXTENSION, $payment);
    }
}
