<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi\Builder;

use Kiener\MolliePayments\Service\MollieApi\PriceCalculator;
use Mollie\Api\Types\OrderLineType;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

class MollieShippingLineItemBuilder
{

    /**
     * @var PriceCalculator
     */
    private $priceCalculator;

    /**
     * @var MollieOrderPriceBuilder
     */
    private $priceHydrator;

    public function __construct(PriceCalculator $priceCalculator, MollieOrderPriceBuilder $priceHydrator)
    {

        $this->priceCalculator = $priceCalculator;
        $this->priceHydrator = $priceHydrator;
    }

    public function buildShippingLineItems(string $taxStatus, OrderDeliveryCollection $deliveries, ?CurrencyEntity $currency): array
    {
        $lineItems = [];

        $currencyCode = MollieOrderPriceBuilder::MOLLIE_FALLBACK_CURRENCY_CODE;
        if ($currency instanceof CurrencyEntity) {
            $currencyCode = $currency->getIsoCode();
        }

        $i = 0;

        /** @var OrderDeliveryEntity $delivery */
        foreach ($deliveries as $delivery) {
            $i++;
            $shippingPrice = $delivery->getShippingCosts();
            $totalPrice = $shippingPrice->getTotalPrice();
            $prices = $this->priceCalculator->calculateLineItemPrice($shippingPrice, $totalPrice, $taxStatus);

            $lineItems[] = [
                'type' => OrderLineType::TYPE_SHIPPING_FEE,
                'name' => 'Delivery costs ' . $i,
                'quantity' => 1,
                'unitPrice' => $this->priceHydrator->build($prices->getUnitPrice(), $currencyCode),
                'totalAmount' => $this->priceHydrator->build($prices->getTotalAmount(), $currencyCode),
                'vatRate' => number_format($prices->getVatRate(), MollieOrderPriceBuilder::MOLLIE_PRICE_PRECISION, '.', ''),
                'vatAmount' => $this->priceHydrator->build($prices->getVatAmount(), $currencyCode),
                'sku' => 'mol-delivery-' . $i,
                'imageUrl' => '',
                'productUrl' => '',
                'metadata' => [
                    'orderLineItemId' => '',
                ],
            ];
        }

        return $lineItems;
    }
}
