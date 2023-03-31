<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi\Builder;

use Kiener\MolliePayments\Service\MollieApi\PriceCalculator;
use Kiener\MolliePayments\Struct\MollieLineItem;
use Kiener\MolliePayments\Struct\MollieLineItemCollection;
use Mollie\Api\Types\OrderLineType;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionEntity;

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
            $totalPrice = $shippingPrice->getTotalPrice();

            if ($totalPrice === 0.0) {
                continue;
            }


            // 3 deliveries
            // 1) qty 2, spedition 40 = 80
            // 2) qty 2, dhl
            // 3) qty 2

            // delivery, Spedition: delivery.price (80 EUR) + 2 positions mit je delivery.position.qty 1 => qty 2


            /*
             *
             * INSERT INTO `order_delivery_position` (`id`, `version_id`, `order_delivery_id`, `order_delivery_version_id`, `order_line_item_id`, `order_line_item_version_id`, `price`, `custom_fields`, `created_at`, `updated_at`) VALUES
             *
(0x30eb766c80874012b4db4672d28d58e1, 0x0fa91ce3e96a4bc2be4bd9ce752c3425, 0x0bde5c044d344d2e95cb451477a523d0, 0x0fa91ce3e96a4bc2be4bd9ce752c3425, 0xa28e4f858bf34a2fb110ee6460b2a4b8, 0x0fa91ce3e96a4bc2be4bd9ce752c3425, '{\"quantity\": 2, \"taxRules\": [{\"taxRate\": 19.0, \"extensions\": [], \"percentage\": 100.0}], \"listPrice\": null, \"unitPrice\": 7.95, \"totalPrice\": 15.9, \"referencePrice\": null, \"calculatedTaxes\": [{\"tax\": 2.54, \"price\": 15.9, \"taxRate\": 19.0, \"extensions\": []}], \"regulationPrice\": null}', NULL, '2023-03-31 07:54:15.882', NULL),
(0x10ff41ebde324e3285314506c264ecc2, 0x0fa91ce3e96a4bc2be4bd9ce752c3425, 0x8b6707ab69114b5888660af27ff30939, 0x0fa91ce3e96a4bc2be4bd9ce752c3425, 0x414fed5650304a5485984f36c71cb78b, 0x0fa91ce3e96a4bc2be4bd9ce752c3425, '{\"quantity\"
             *
             *
             *
             */
            if ($delivery->getPositions() instanceof DeliveryPositionCollection && $delivery->getPositions()->count() >= 1) {

                $subIndex = 10;

                /** @var OrderDeliveryPositionEntity $position */
                foreach ($delivery->getPositions() as $position) {

                    $qty = $position->getQuantity();
                    $unitPrice = $position->getUnitPrice();

                    $mollieLineItem = new MollieLineItem(
                        OrderLineType::TYPE_SHIPPING_FEE,
                        sprintf('Delivery costs %s', $subIndex),
                        $qty,
                        $unitPrice,
                        $delivery->getId(),
                        sprintf('mol-delivery-%s', $subIndex),
                        '',
                        ''
                    );

                    $lines->add($mollieLineItem);

                    $subIndex++;
                }
            } else {
                $unitPrice = $this->priceCalculator->calculateLineItemPrice($shippingPrice, $totalPrice, $taxStatus, $isVerticalTaxCalculation);

                $mollieLineItem = new MollieLineItem(
                    OrderLineType::TYPE_SHIPPING_FEE,
                    sprintf('Delivery costs %s', $i),
                    1,
                    $unitPrice,
                    $delivery->getId(),
                    sprintf('mol-delivery-%s', $i),
                    '',
                    ''
                );

                $lines->add($mollieLineItem);
            }


        }

        return $lines;
    }
}
