<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi\Builder;

use Kiener\MolliePayments\Exception\MissingPriceLineItem;
use Kiener\MolliePayments\Service\MollieApi\LineItemDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\PriceCalculator;
use Kiener\MolliePayments\Struct\MollieLineItem;
use Kiener\MolliePayments\Struct\MollieLineItemCollection;
use Kiener\MolliePayments\Validator\IsOrderLineItemValid;
use Mollie\Api\Types\OrderLineType;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class MollieLineItemBuilder
{
    public const LINE_ITEM_TYPE_CUSTOM_PRODUCTS = 'customized-products';

    /**
     * @var IsOrderLineItemValid
     */
    private $orderLineItemValidator;

    /**
     * @var PriceCalculator
     */
    private $priceCalculator;

    /**
     * @var LineItemDataExtractor
     */
    private $lineItemDataExtractor;


    public function __construct(
        IsOrderLineItemValid $orderLineItemValidator,
        PriceCalculator $priceCalculator,
        LineItemDataExtractor $lineItemDataExtractor
    )
    {
        $this->orderLineItemValidator = $orderLineItemValidator;
        $this->priceCalculator = $priceCalculator;
        $this->lineItemDataExtractor = $lineItemDataExtractor;
    }

    public function buildLineItems(string $taxStatus, ?OrderLineItemCollection $lineItems, bool $isVerticalTaxCalculation = false): MollieLineItemCollection
    {
        $lines = new MollieLineItemCollection();

        if (!$lineItems instanceof OrderLineItemCollection || $lineItems->count() === 0) {

            return $lines;
        }

        /** @var OrderLineItemEntity $item */
        foreach ($lineItems as $item) {
            $this->orderLineItemValidator->validate($item);
            $extraData = $this->lineItemDataExtractor->extractExtraData($item);
            $itemPrice = $item->getPrice();

            if (!$itemPrice instanceof CalculatedPrice) {
                throw new MissingPriceLineItem($item->getProductId());
            }

            $price = $this->priceCalculator->calculateLineItemPrice($item->getPrice(), $item->getTotalPrice(), $taxStatus, $isVerticalTaxCalculation);

            $mollieLineItem = new MollieLineItem(
                $this->getLineItemType($item),
                $item->getLabel(),
                $item->getQuantity(),
                $price,
                $item->getId(),
                $extraData->getSku(),
                urlencode((string)$extraData->getImageUrl()),
                urlencode((string)$extraData->getProductUrl())
            );

            $lines->add($mollieLineItem);
        }

        return $lines;
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

        if ($item->getType() === LineItem::PROMOTION_LINE_ITEM_TYPE ||
            $item->getTotalPrice() < 0) {
            return OrderLineType::TYPE_DISCOUNT;
        }

        if ($item->getType() === self::LINE_ITEM_TYPE_CUSTOM_PRODUCTS) {
            return OrderLineType::TYPE_PHYSICAL;
        }

        return OrderLineType::TYPE_DIGITAL;
    }
}
