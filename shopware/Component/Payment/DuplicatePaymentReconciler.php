<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Mollie\CreatePaymentRefund;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGateway;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGatewayInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A checkout can leave more than one Mollie payment on an order: whenever the customer switches the
 * payment method, Shopware creates a new order transaction (with its own Mollie payment) and cancels
 * the previous one. If the customer then completes an abandoned attempt from an old tab, that older
 * payment gets paid too.
 *
 * Once one payment reaches "paid", this reconciler walks the order's other transactions, reads the
 * live Mollie status of each (independent of the Shopware transaction state, which Shopware may have
 * cancelled), and resolves them: refund the paid ones, cancel the cancelable ones, leave the open
 * ones (Mollie cannot cancel/refund those).
 *
 * Same-method retries do not create a duplicate: they reuse the existing Mollie payment (see Pay),
 * so there is only ever one payment per transaction to reason about here.
 *
 * This is best-effort cleanup: it must never disturb the just-completed checkout, so every Mollie
 * API call is caught individually and the method never throws.
 */
final class DuplicatePaymentReconciler implements DuplicatePaymentReconcilerInterface
{
    /**
     * @param EntityRepository<OrderTransactionCollection<OrderTransactionEntity>> $orderTransactionRepository
     */
    public function __construct(
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        #[Autowire(service: RefundGateway::class)]
        private readonly RefundGatewayInterface $refundGateway,
        #[Autowire(service: 'order_transaction.repository')]
        private readonly EntityRepository $orderTransactionRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function reconcile(OrderEntity $order, string $currentTransactionId, Context $context): void
    {
        $currentTransactionId = strtolower($currentTransactionId);
        $orderNumber = (string) $order->getOrderNumber();
        $salesChannelId = $order->getSalesChannelId();

        $transactions = $order->getTransactions();
        if ($transactions === null) {
            return;
        }

        $upsertData = [];
        foreach ($transactions as $transaction) {
            if (strtolower($transaction->getId()) === $currentTransactionId) {
                continue;
            }

            $payment = $transaction->getExtension(Mollie::EXTENSION);
            if (! $payment instanceof Payment) {
                continue;
            }

            // A transaction handled by a previous run is left alone, so we do not call the Mollie
            // API again for a payment that is already cancelled/refunded.
            if ($payment->isReconciled()) {
                continue;
            }

            if ($this->reconcilePayment($payment->getId(), (string) $payment->getOrderId(), $orderNumber, $salesChannelId)) {
                $upsertData[] = $this->buildReconciledUpsert($transaction);
            }
        }

        if ($upsertData !== []) {
            $this->orderTransactionRepository->upsert($upsertData, $context);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReconciledUpsert(OrderTransactionEntity $transaction): array
    {
        $mollieCustomFields = ($transaction->getCustomFields() ?? [])[Mollie::EXTENSION] ?? [];
        if (! is_array($mollieCustomFields)) {
            $mollieCustomFields = [];
        }
        $mollieCustomFields['reconciled'] = true;

        return [
            'id' => $transaction->getId(),
            'customFields' => [Mollie::EXTENSION => $mollieCustomFields],
        ];
    }

    private function reconcilePayment(string $molliePaymentId, string $mollieOrderId, string $orderNumber, string $salesChannelId): bool
    {
        $logData = [
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
            'molliePaymentId' => $molliePaymentId,
            'mollieOrderId' => $mollieOrderId,
        ];

        try {
            $payment = $this->mollieGateway->getPayment($molliePaymentId, $orderNumber, $salesChannelId);
            $status = $payment->getStatus();
            $logData['paymentStatus'] = $status->value;

            if ($status === PaymentStatus::PAID) {
                $this->refund($payment, $orderNumber, $salesChannelId, $logData);

                return true;
            }

            if ($payment->isCancelable()) {
                $this->mollieGateway->cancelPayment($molliePaymentId, $orderNumber, $salesChannelId);
                $this->logger->info('Cancelled duplicate Mollie payment', $logData);

                return true;
            }

            if ($mollieOrderId !== '' && $status->isApproved()) {
                $this->mollieGateway->cancelOrder($mollieOrderId, $orderNumber, $salesChannelId);
                $this->logger->info('Cancelled duplicate Mollie order', $logData);

                return true;
            }

            $this->logger->info('Duplicate payment needs no action, skipping', $logData);

            return true;
        } catch (\Throwable $exception) {
            $logData['exceptionMessage'] = $exception->getMessage();
            $this->logger->error('Failed to reconcile duplicate payment, continuing with remaining transactions', $logData);

            return false;
        }
    }

    /**
     * @param array<string, string> $logData
     */
    private function refund(Payment $payment, string $orderNumber, string $salesChannelId, array $logData): void
    {
        $amount = $payment->getAmountRemaining() ?? $payment->getAmount();
        if (! $amount instanceof Money || $amount->getValue() <= 0.0) {
            $this->logger->warning('Duplicate payment has no refundable amount, skipping', $logData);

            return;
        }

        $description = sprintf('Automatic refund of superseded duplicate payment for order %s', $orderNumber);
        $createRefund = new CreatePaymentRefund($payment->getId(), $amount, $description);
        $this->refundGateway->createRefund($createRefund, $orderNumber, $salesChannelId);

        $logData['refundAmount'] = (string) $amount->getValue();
        $this->logger->info('Refunded duplicate Mollie payment', $logData);
    }
}
