<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\RefundCalculationHelper;

class RefundCalculationHelper
{
    /** @var array */
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
     * @return array
     */
    public function getRefundArray(): array
    {
        return $this->refundArray;
    }

    /**
     * @param $orderLineId
     * @return int
     */
    public function getRefundQuantityForMollieId($orderLineId): int
    {
        if (isset($this->refundArray[$orderLineId])) {
            return $this->refundArray[$orderLineId];
        }
        return 0;
    }
}
