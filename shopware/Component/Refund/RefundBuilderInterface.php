<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund;

use Mollie\Shopware\Component\Mollie\CreateRefund;
use Mollie\Shopware\Component\Mollie\Payment;
use Shopware\Core\Checkout\Order\OrderEntity;

interface RefundBuilderInterface
{
    /**
     * @param array<array{id: string, quantity: int, amount: float, resetStock: int}> $requestItems
     */
    public function build(Payment $payment, OrderEntity $order, array $requestItems, string $description, ?float $requestAmount = null): CreateRefund;
}
