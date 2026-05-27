<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Mollie;

final class RefundCollection implements \JsonSerializable
{
    /** @var Refund[] */
    private array $refunds = [];

    public function add(Refund $refund): void
    {
        $this->refunds[] = $refund;
    }

    public function getSumRefunded(): float
    {
        $total = 0.0;
        foreach ($this->refunds as $refund) {
            if ($refund->getStatus() === RefundStatus::Refunded) {
                $total += (float) $refund->getAmount()->getValue();
            }
        }

        return round($total, Mollie::ROUNDING_PRECISION);
    }

    public function getSumPending(): float
    {
        $total = 0.0;
        foreach ($this->refunds as $refund) {
            $status = $refund->getStatus();
            if ($status === RefundStatus::Pending || $status === RefundStatus::Queued) {
                $total += (float) $refund->getAmount()->getValue();
            }
        }

        return round($total, Mollie::ROUNDING_PRECISION);
    }

    public function findByMollieId(string $mollieRefundId): ?Refund
    {
        foreach ($this->refunds as $refund) {
            if ($refund->getId() === $mollieRefundId) {
                return $refund;
            }
        }

        return null;
    }

    /**
     * @return Refund[]
     */
    public function jsonSerialize(): array
    {
        return $this->refunds;
    }
}
