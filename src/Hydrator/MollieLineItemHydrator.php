<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Hydrator;

use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Struct\MollieLineItemCollection;


class MollieLineItemHydrator
{

    /**
     * @var MollieOrderPriceBuilder
     */
    private $priceBuilder;


    /**
     * @param MollieOrderPriceBuilder $priceBuilder
     */
    public function __construct(MollieOrderPriceBuilder $priceBuilder)
    {
        $this->priceBuilder = $priceBuilder;
    }

    /**
     * @param MollieLineItemCollection $lineItems
     * @param string $currencyCode
     * @return array<int,array<string,mixed>>
     */
    public function hydrate(MollieLineItemCollection $lineItems, string $currencyCode): array
    {
        $lines = [];

        foreach ($lineItems as $lineItem) {
            $price = $lineItem->getPrice();

            $lines[] = [
                'type' => $lineItem->getType(),
                'name' => $lineItem->getName(),
                'quantity' => $lineItem->getQuantity(),
                'unitPrice' => $this->priceBuilder->build($price->getUnitPrice(), $currencyCode),
                'totalAmount' => $this->priceBuilder->build($price->getTotalAmount(), $currencyCode),
                'vatRate' => number_format($price->getVatRate(), MollieOrderPriceBuilder::MOLLIE_PRICE_PRECISION, '.', ''),
                'vatAmount' => $this->priceBuilder->build($price->getVatAmount(), $currencyCode),
                'sku' => $lineItem->getSku(),
                'imageUrl' => $lineItem->getImageUrl(),
                'productUrl' => $lineItem->getProductUrl(),
                'metadata' => [
                    'orderLineItemId' => $lineItem->getLineItemId(),
                ],
            ];
        }

        return $lines;
    }

}
