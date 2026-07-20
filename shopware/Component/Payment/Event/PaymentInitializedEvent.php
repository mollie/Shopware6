<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Event;

use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Shopware\Core\Framework\Context;

/**
 * Base event for "a payment was initialized for an order", independent of how: a regular checkout
 * payment ({@see PaymentCreatedEvent}) or a payment link ({@see PaymentLinkCreatedEvent}). Carries
 * only the order data every initialization has; the concrete events add their specifics.
 */
abstract class PaymentInitializedEvent
{
    public function __construct(
        private readonly TransactionDataStruct $transactionDataStruct,
        private readonly Context $context,
    ) {
    }

    public function getTransactionDataStruct(): TransactionDataStruct
    {
        return $this->transactionDataStruct;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
