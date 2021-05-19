<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;


use Kiener\MolliePayments\Hydrator\MolliePriceHydrator;
use Kiener\MolliePayments\Struct\LineItemExtraData;
use Kiener\MolliePayments\Struct\LineItemPriceStruct;
use Kiener\MolliePayments\Validator\OrderLineItemValidator;
use Mollie\Api\Types\OrderLineType;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class MollieOrderLineItemBuilder
{
    public const MOLLIE_PRICE_PRECISION = 2;

    public const LINE_ITEM_TYPE_CUSTOM_PRODUCTS = 'customized-products';

    /**
     * @var MolliePriceHydrator
     */
    private $priceHydrator;
    /**
     * @var OrderLineItemValidator
     */
    private $validator;

    public function __construct(MolliePriceHydrator $priceHydrator, OrderLineItemValidator $validator)
    {

        $this->priceHydrator = $priceHydrator;
        $this->validator = $validator;
    }

    public function buildLineItems(OrderEntity $order): array
    {
        $lines = [];
        $lineItems = $order->getNestedLineItems();

        if (!$lineItems instanceof OrderLineItemCollection || $lineItems->count() === 0) {
            return $lines;
        }

        $currencyCode = $order->getCurrency()->getIsoCode() ?? MolliePriceHydrator::MOLLIE_FALLBACK_CURRENCY_CODE;

        /** @var OrderLineItemEntity $item */
        foreach ($lineItems as $item) {
            $this->validator->validate($item);
            $extraData = $this->extractExtraData($item);
            $prices = $this->calculateLineItemPrice($item, $order->getTaxStatus());

            $lines[] = [
                'type' => $this->getLineItemType($item),
                'name' => $item->getLabel(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $this->priceHydrator->hydrate($prices->getUnitPrice(), $currencyCode),
                'totalAmount' => $this->priceHydrator->hydrate($prices->getTotalAmount(), $currencyCode),
                'vatRate' => number_format($prices->getVatRate(), self::MOLLIE_PRICE_PRECISION, '.', ''),
                'vatAmount' => $this->priceHydrator->hydrate($prices->getVatAmount(), $currencyCode),
                'sku' => $extraData->getSku(),
                'imageUrl' => urlencode($extraData->getImageUrl()),
                'productUrl' => urlencode($extraData->getProductUrl()),
                'metadata' => [
                    'orderLineItemId' => $item->getId(),
                ],
            ];
        }

        return $lines;
    }

    public function calculateLineItemPrice(OrderLineItemEntity $item, string $orderTaxType): LineItemPriceStruct
    {
        $price = $item->getPrice();
        $taxCollection = $price->getCalculatedTaxes();

        $vatRate = 0.0;
        $itemTax = $this->calculateMixedTax($taxCollection);
        if ($itemTax instanceof CalculatedTax) {
            $vatRate = $itemTax->getTaxRate();
        }

        // Remove VAT if the order is tax free
        if ($orderTaxType === CartPrice::TAX_STATE_FREE) {
            $vatRate = 0.0;
        }

        $unitPrice = $price->getUnitPrice();
        $lineItemTotalPrice = $item->getTotalPrice();

        // If the order is of type TAX_STATE_NET the $lineItemTotalPrice and unit price
        // is a net price.
        // For correct mollie api tax calculations we have to calculate the shopware gross
        // price
        if ($orderTaxType === CartPrice::TAX_STATE_NET) {
            $unitPrice *= ((100 + $vatRate) / 100);
            $lineItemTotalPrice += $taxCollection->getAmount();
        }

        $unitPrice = round($unitPrice, self::MOLLIE_PRICE_PRECISION);

        $roundedLineItemTotalPrice = round($lineItemTotalPrice, self::MOLLIE_PRICE_PRECISION);
        $roundedVatRate = round($vatRate, self::MOLLIE_PRICE_PRECISION);
        $vatAmount = $roundedLineItemTotalPrice * ($roundedVatRate / (100 + $roundedVatRate));
        $roundedVatAmount = round($vatAmount, self::MOLLIE_PRICE_PRECISION);

        return new LineItemPriceStruct($unitPrice, $roundedLineItemTotalPrice, $roundedVatAmount, $roundedVatRate);
    }

    public function extractExtraData(OrderLineItemEntity $lineItem): LineItemExtraData
    {
        $product = $lineItem->getProduct();

        if (!$product instanceof ProductEntity) {
            return new LineItemExtraData();
        }

        $extraData = new LineItemExtraData();

        $extraData->setSku($product->getProductNumber());

        $medias = $product->getMedia();
        if ($medias instanceof ProductMediaCollection
            && $medias->first() instanceof ProductMediaEntity
            && $medias->first()->getMedia() instanceof MediaEntity
        ) {
            $extraData->setImageUrl($medias->first()->getMedia()->getUrl());
        }

        $seoUrls = $product->getSeoUrls();
        if ($seoUrls instanceof SeoUrlCollection
            && $seoUrls->first() instanceof SeoUrlEntity
        ) {
            $extraData->setProductUrl($seoUrls->first()->getUrl());
        }

        return $extraData;
    }

    /**
     * Return a calculated tax struct for a line item. The tax rate is recalculated from multiple taxRates to
     * one taxRate that will fit for the lineItem
     *
     * @param CalculatedTaxCollection $taxCollection
     * @return CalculatedTax|null
     */
    public function calculateMixedTax(CalculatedTaxCollection $taxCollection): ?CalculatedTax
    {
        if ($taxCollection->count() === 0) {
            return null;
        }

        if ($taxCollection->count() === 1) {
            return $taxCollection->first();
        }

        $tax = [
            'tax' => 0,
            'taxRate' => 0,
            'price' => 0,
        ];

        $taxCollection->map(static function (CalculatedTax $calculatedTax) use (&$tax) {
            $tax['tax'] += $calculatedTax->getTax();
            $tax['price'] += $calculatedTax->getPrice();
        });

        if ($tax['price'] !== $tax['tax']) {
            $tax['taxRate'] = $tax['tax'] / ($tax['price'] - $tax['tax']);
        }

        return new CalculatedTax(
            $tax['tax'],
            round($tax['taxRate'], 4) * 100,
            $tax['price']
        );
    }

    /**
     * Return the type of the line item.
     *
     * @param OrderLineItemEntity $item
     * @return string|null
     */
    public function getLineItemType(OrderLineItemEntity $item): ?string
    {
        if ($item->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
            return OrderLineType::TYPE_PHYSICAL;
        }

        if ($item->getType() === LineItem::CREDIT_LINE_ITEM_TYPE) {
            return OrderLineType::TYPE_STORE_CREDIT;
        }

        if ($item->getType() === PromotionProcessor::LINE_ITEM_TYPE ||
            $item->getTotalPrice() < 0) {
            return OrderLineType::TYPE_DISCOUNT;
        }

        if ($item->getType() === self::LINE_ITEM_TYPE_CUSTOM_PRODUCTS) {
            return OrderLineType::TYPE_PHYSICAL;
        }

        return OrderLineType::TYPE_DIGITAL;
    }
}
