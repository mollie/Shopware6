<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi\Builder;

use Kiener\MolliePayments\Service\MollieApi\PriceCalculator;
use Kiener\MolliePayments\Struct\MollieLineItem;
use Kiener\MolliePayments\Struct\MollieLineItemCollection;
use Mollie\Api\Types\OrderLineType;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;

class MollieShippingLineItemBuilder
{
    /**
     * @var PriceCalculator
     */
    private $priceCalculator;


    /**
     * @param PriceCalculator $priceCalculator
     */
    public function __construct(PriceCalculator $priceCalculator)
    {
        $this->priceCalculator = $priceCalculator;
    }


    /**
     * @param string $taxStatus
     * @param OrderDeliveryCollection $deliveries
     * @param bool $isVerticalTaxCalculation
     * @return MollieLineItemCollection
     */
    public function buildShippingLineItems(string $taxStatus, OrderDeliveryCollection $deliveries, bool $isVerticalTaxCalculation = false): MollieLineItemCollection
    {
        $lines = new MollieLineItemCollection();

        $i = 0;

        /** @var OrderDeliveryEntity $delivery */
        foreach ($deliveries as $delivery) {
            $i++;
            $shippingPrice = $delivery->getShippingCosts();
            $qty = $shippingPrice->getQuantity();
            $totalPrice = $shippingPrice->getTotalPrice();

            if ($totalPrice === 0.0) {
                continue;
            }

            $price = $this->priceCalculator->calculateLineItemPrice($shippingPrice, $totalPrice, $taxStatus, $isVerticalTaxCalculation);

            $mollieLineItem = new MollieLineItem(
                OrderLineType::TYPE_SHIPPING_FEE,
                sprintf('Delivery costs %s', $i),
                $qty,
                $price,
                $delivery->getId(),
                sprintf('mol-delivery-%s', $i),
                '',
                ''
            );

            $lines->add($mollieLineItem);
        }

        return $lines;
    }
}
