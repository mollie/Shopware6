<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface DuplicatePaymentReconcilerInterface
{
    /**
     * After a payment for an order has been completed, cancel or refund the Mollie payments of the
     * order's other transactions (identified by their live Mollie status) so only the just-paid
     * transaction stays settled, and flag the handled transactions so later runs skip them.
     */
    public function reconcile(OrderEntity $order, string $currentTransactionId, Context $context): void;
}
