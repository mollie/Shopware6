<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Refund\Struct\RefundTotalsStruct;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Computes what is still refundable for an order. Every refund route answers with these totals,
 * so the caps below are decided in one place instead of three.
 *
 * Not final: the routes are unit tested against a fake of this class.
 */
class RefundTotalsBuilder
{
    public function __construct(
        private readonly RefundableTotalCalculator $refundableTotalCalculator,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function build(OrderEntity $order, Payment $payment, Payment $freshPayment): RefundTotalsStruct
    {
        $refunds = $freshPayment->getRefunds();
        $amountRefunded = $refunds->getSumRefunded();
        $amountPending = $refunds->getSumPending();
        // Use the original refundable total (non-credit line items + shipping), NOT
        // order->getAmountTotal(): credit notes add a negative credit line item and
        // recalculate the order, which would otherwise shrink the total on every refund.
        $refundableTotal = $this->refundableTotalCalculator->calculate($order);
        $remaining = max(0.0, $refundableTotal - $amountRefunded - $amountPending);

        // Mollie can only refund captured money, so a manual capture method that captured less
        // than the order total (e.g. after a cancellation) is the real ceiling. Order API refunds
        // are line item based, there Mollie derives the amount itself.
        $mollieRefundable = $payment->getOrderId() === null ? $freshPayment->getRefundableAmount() : null;
        if ($mollieRefundable !== null) {
            $remaining = min($remaining, max(0.0, $mollieRefundable));
        }

        $this->logger->debug('Refund totals computed', [
            'orderNumber' => $order->getOrderNumber(),
            'amountTotal' => $order->getAmountTotal(),
            'refundableTotal' => $refundableTotal,
            'refunded' => $amountRefunded,
            'pending' => $amountPending,
            'mollieRefundable' => $mollieRefundable,
            'remaining' => $remaining,
            'mollieRefunds' => array_map(function ($refund) {
                return ['amount' => $refund->getAmount()->getValue(), 'status' => $refund->getStatus()->value];
            }, $refunds->jsonSerialize()),
        ]);

        $totals = new RefundTotalsStruct();
        $totals->setRefunded($amountRefunded);
        $totals->setPendingRefunds($amountPending);
        $totals->setRemaining($remaining);
        $totals->setVoucherAmount($payment->getVoucherAmount());
        $totals->setRoundingDiff($payment->getRoundingDiff());

        return $totals;
    }
}
