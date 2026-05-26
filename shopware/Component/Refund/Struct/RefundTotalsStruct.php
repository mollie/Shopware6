<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Struct;

use Mollie\Shopware\Mollie;
use Shopware\Core\Framework\Struct\Struct;

final class RefundTotalsStruct extends Struct
{
    protected float $remaining = 0.0;
    protected float $refunded = 0.0;
    protected float $voucherAmount = 0.0;
    protected float $pendingRefunds = 0.0;
    protected float $roundingDiff = 0.0;

    public function getApiAlias(): string
    {
        return 'mollie_refund_totals';
    }

    public function getRemaining(): float
    {
        return $this->remaining;
    }

    public function setRemaining(float $remaining): void
    {
        $this->remaining = round($remaining, Mollie::ROUNDING_PRECISION);
    }

    public function getRefunded(): float
    {
        return $this->refunded;
    }

    public function setRefunded(float $refunded): void
    {
        $this->refunded = round($refunded, Mollie::ROUNDING_PRECISION);
    }

    public function getVoucherAmount(): float
    {
        return $this->voucherAmount;
    }

    public function setVoucherAmount(float $voucherAmount): void
    {
        $this->voucherAmount = round($voucherAmount, Mollie::ROUNDING_PRECISION);
    }

    public function getPendingRefunds(): float
    {
        return $this->pendingRefunds;
    }

    public function setPendingRefunds(float $pendingRefunds): void
    {
        $this->pendingRefunds = round($pendingRefunds, Mollie::ROUNDING_PRECISION);
    }

    public function getRoundingDiff(): float
    {
        return $this->roundingDiff;
    }

    public function setRoundingDiff(float $roundingDiff): void
    {
        $this->roundingDiff = round($roundingDiff, Mollie::ROUNDING_PRECISION);
    }
}
