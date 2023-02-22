<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\RefundCalculationHelper;

use Kiener\MolliePayments\Service\Refund\Item\RefundItem;

class RefundCalculationHelper
{
    /** @var RefundItem[] */
    protected $refundItems;

    public function __construct()
    {
        $this->refundItems = [];
    }

    /**
     * @param RefundItem $refundItem
     * @return void
     */
    public function addRefundItem(RefundItem $refundItem)
    {
        $this->refundItems[] = $refundItem;
    }

    /**
     * @param string $orderLineId
     * @return int
     */
    public function getRefundQuantityForMollieId(string $orderLineId): int
    {
        $refundQuantity = 0;
        foreach ($this->refundItems as $refundItem) {
            if ($refundItem->getMollieLineID()===$orderLineId){
                $refundQuantity += $refundItem->getQuantity();
            }
        }
        return $refundQuantity;
    }

    /**
     * @param string $orderLineId
     * @return float
     */
    public function getRefundAmountForMollieId(string $orderLineId): float
    {
        $refundAmount = 0;
        foreach ($this->refundItems as $refundItem) {
            if ($refundItem->getMollieLineID()===$orderLineId){
                $refundAmount += $refundItem->getAmount();
            }
        }
        return $refundAmount;
    }
}
