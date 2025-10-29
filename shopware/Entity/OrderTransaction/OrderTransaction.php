<?php
declare(strict_types=1);

namespace Mollie\Shopware\Entity\OrderTransaction;

use Shopware\Core\Framework\Struct\ArrayStruct;

final class OrderTransaction extends ArrayStruct
{
    public const PAYMENTS_API_FLAG = 'paymentsApi';

    public function __construct(private string $paymentId, private string $finalizeUrl, private int $countPayments = 1, private bool $paymentsApi = true)
    {
        parent::__construct([
            self::PAYMENTS_API_FLAG => $this->paymentsApi,
            'paymentId' => $this->paymentId,
            'finalizeUrl' => $this->finalizeUrl,
            'countPayments' => $this->countPayments
        ], 'mollie_order_transaction');
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getFinalizeUrl(): string
    {
        return $this->finalizeUrl;
    }

    public function getCountPayments(): int
    {
        return $this->countPayments;
    }
}
