<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdInvoiceGeneratedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Feature;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ZugferdInvoiceGeneratedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ZugferdInvoiceGeneratedEvent::class => 'onInvoiceGenerated',
        ];
    }

    public function onInvoiceGenerated(ZugferdInvoiceGeneratedEvent $event): void
    {
        $transaction = $this->resolveTransaction($event->order);
        if (! $transaction instanceof OrderTransactionEntity) {
            return;
        }

        $payment = $transaction->getExtension(Mollie::EXTENSION);
        if (! $payment instanceof Payment) {
            return;
        }

        $method = $payment->getMethod();
        if ($method === null) {
            return;
        }

        // Shopware only fills SpecifiedTradeSettlementPaymentMeans for its own cash/invoice/prepayment
        // methods, so Mollie payments would ship without the block. Add it based on the Mollie method.
        $event->document->getBuilder()->addDocumentPaymentMean(
            typeCode: (string) $method->eInvoicePaymentMeansCode(),
            information: $transaction->getPaymentMethod()?->getName()
        );
    }

    private function resolveTransaction(OrderEntity $order): ?OrderTransactionEntity
    {
        if (Feature::has('v6.8.0.0') && Feature::isActive('v6.8.0.0')) {
            return $order->getPrimaryOrderTransaction();
        }

        return $order->getTransactions()?->last();
    }
}
