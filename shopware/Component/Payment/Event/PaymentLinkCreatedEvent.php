<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Event;

use Mollie\Shopware\Component\Transaction\TransactionDataStruct;
use Shopware\Core\Framework\Context;

/**
 * Dispatched when a Mollie payment link has been created for an order. Unlike
 * {@see PaymentCreatedEvent} there is no Mollie payment yet (the customer has not paid), so it only
 * carries the order data.
 */
final class PaymentLinkCreatedEvent extends PaymentInitializedEvent
{
    public function __construct(
        private readonly string $paymentLinkUrl,
        TransactionDataStruct $transactionDataStruct,
        Context $context
    ) {
        parent::__construct($transactionDataStruct, $context);
    }

    public function getPaymentLinkUrl(): string
    {
        return $this->paymentLinkUrl;
    }
}
