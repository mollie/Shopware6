<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\RefundData\OrderItem;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;

class DeliveryItem extends AbstractItem
{
    /**
     * @var OrderDeliveryEntity
     */
    private $delivery;

    /**
     * @var int
     */
    private $alreadyRefundedQty;

    public function __construct(OrderDeliveryEntity $delivery, int $alreadyRefundedQuantity, float $taxTotal, float $taxPerItem, float $taxDiff)
    {
        $this->delivery = $delivery;
        $this->alreadyRefundedQty = $alreadyRefundedQuantity;

        parent::__construct($taxTotal, $taxPerItem, $taxDiff);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $label = ($this->delivery->getShippingMethod() !== null) ? (string) $this->delivery->getShippingMethod()->getName() : 'UNKNOWN NAME';

        return $this->buildArray(
            $this->delivery->getId(),
            $label,
            'SHIPPING',
            false,
            true,
            $this->delivery->getShippingCosts()->getUnitPrice(),
            $this->delivery->getShippingCosts()->getQuantity(),
            $this->delivery->getShippingCosts()->getTotalPrice(),
            0,
            0,
            0,
            $this->alreadyRefundedQty
        );
    }
}
