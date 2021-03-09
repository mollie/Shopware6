<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Shopware\Core\Checkout\Order\OrderEntity;

class RefundService
{
    public const CF_REFUNDED_AMOUNT = 'refundedAmount';
    public const CF_REFUNDED_QUANTITY = 'refundedQuantity';

    /** @var MollieApiFactory */
    private $apiFactory;

    /** @var OrderService */
    private $orderService;

    /**
     * CustomFieldService constructor.
     *
     * @param OrderService $orderService
     */
    public function __construct(
        OrderService $orderService
    )
    {
        $this->orderService = $orderService;
    }

    public function getRefundedAmount(OrderEntity $order): float
    {

        $customFields = $order->getCustomFields();

        if (is_null($customFields)) {
            return 0.0;
        }

        if (array_key_exists(CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS, $customFields)) {
            $refundedAmount = $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][self::CF_REFUNDED_AMOUNT]
                ?? 0.0;
        } else {
            $refundedAmount = $this->getRefundedAmountFromLineitems($order);
        }

        return $refundedAmount;
    }

    public function getRefundableAmount(OrderEntity $order): float
    {
        return $order->getAmountTotal() - $this->getRefundedAmount($order);
    }

    /**
     * function for getting the refunded amount from older orders, when order refund requests were used
     * @param OrderEntity $order
     * @return float
     */
    private function getRefundedAmountFromLineitems(OrderEntity $order): float {
        $amount = 0.0;

        foreach ($order->getLineItems() as $lineItem) {
            if (
                !empty($lineItem->getCustomFields())
                && isset($lineItem->getCustomFields()[self::CF_REFUNDED_QUANTITY])
            ) {
                $amount += ($lineItem->getUnitPrice() * (int)$lineItem->getCustomFields()[self::CF_REFUNDED_QUANTITY]);
            }
        }

        return $amount;
    }
}
