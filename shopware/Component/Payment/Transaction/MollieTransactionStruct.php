<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Transaction;

class MollieTransactionStruct
{
    public function __construct(
        private string $orderTransactionId,
        private string $returnUrl
    ) {
    }

    public function getOrderTransactionId(): string
    {
        return $this->orderTransactionId;
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }
}
