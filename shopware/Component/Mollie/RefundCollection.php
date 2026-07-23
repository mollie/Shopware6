<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Mollie;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Refund>
 */
final class RefundCollection extends Collection
{
    public function getSumRefunded(): float
    {
        $total = 0.0;
        foreach ($this->elements as $refund) {
            if ($refund->getStatus() === RefundStatus::Refunded) {
                $total += (float) $refund->getAmount()->getValue();
            }
        }

        return round($total, Mollie::ROUNDING_PRECISION);
    }

    public function getSumPending(): float
    {
        $total = 0.0;
        foreach ($this->elements as $refund) {
            $status = $refund->getStatus();
            if ($status === RefundStatus::Pending || $status === RefundStatus::Queued) {
                $total += (float) $refund->getAmount()->getValue();
            }
        }

        return round($total, Mollie::ROUNDING_PRECISION);
    }

    public function findByMollieId(string $mollieRefundId): ?Refund
    {
        foreach ($this->elements as $refund) {
            if ($refund->getId() === $mollieRefundId) {
                return $refund;
            }
        }

        return null;
    }

    public function findByReturnId(string $returnId): ?Refund
    {
        foreach ($this->elements as $refund) {
            if ($refund->getReturnId() === $returnId) {
                return $refund;
            }
        }

        return null;
    }
}
