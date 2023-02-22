<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\RefundCalculationHelper;

class RefundCalculationHelper
{
    /** @var array<string,int> */
    protected $refundArray;

    public function __construct()
    {
        $this->refundArray = [];
    }

    /**
     * @param string $mollieLineId
     * @param int $quantity
     * @return void
     */
    public function addRefund(string $mollieLineId, int $quantity)
    {
        if (isset($this->refundArray[$mollieLineId])) {
            $this->refundArray[$mollieLineId] = $this->refundArray[$mollieLineId] + $quantity;
        } else {
            $this->refundArray[$mollieLineId] = $quantity;
        }
    }

    /**
     * @param string $orderLineId
     * @return int
     */
    public function getRefundQuantityForMollieId(string $orderLineId): int
    {
        if (isset($this->refundArray[$orderLineId])) {
            return $this->refundArray[$orderLineId];
        }
        return 0;
    }
}
