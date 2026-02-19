<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi\Builder;

use Kiener\MolliePayments\Exception\MissingPriceLineItemException;
use Kiener\MolliePayments\Hydrator\MollieLineItemHydrator;
use Kiener\MolliePayments\Service\MollieApi\Fixer\RoundingDifferenceFixer;
use Kiener\MolliePayments\Service\MollieApi\LineItemDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\PriceCalculator;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Struct\MollieLineItem;
use Kiener\MolliePayments\Struct\MollieLineItemCollection;
use Kiener\MolliePayments\Validator\IsOrderLineItemValid;
use Mollie\Api\Types\OrderLineType;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class MollieLineItemBuilder
{
    public const LINE_ITEM_TYPE_CUSTOM_PRODUCTS = 'customized-products';
    public const LINE_ITEM_TYPE_CUSTOM_PRODUCTS_OPTIONS = 'customized-products-option';
    public const LINE_ITEM_REPERTUS_SET = 'repertus_product_container';

    public const LINE_ITEM_DREISEC_SET = 'dreisc-set';

    public const LINE_ITEM_SKWEB_SET = 'swkweb-product-set';

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

    /**
     * @var RoundingDifferenceFixer
     */
    private $roundingDiffFixer;

    /**
     * @var MollieLineItemHydrator
     */
    private $mollieLineItemHydrator;

    /**
     * @var MollieShippingLineItemBuilder
     */
    private $shippingLineItemBuilder;

    public function __construct(IsOrderLineItemValid $orderLineItemValidator, PriceCalculator $priceCalculator, LineItemDataExtractor $lineItemDataExtractor, RoundingDifferenceFixer $orderAmountFixer, MollieLineItemHydrator $mollieLineItemHydrator, MollieShippingLineItemBuilder $shippingLineItemBuilder)
    {
        $this->orderLineItemValidator = $orderLineItemValidator;
        $this->priceCalculator = $priceCalculator;
        $this->lineItemDataExtractor = $lineItemDataExtractor;
        $this->roundingDiffFixer = $orderAmountFixer;
        $this->mollieLineItemHydrator = $mollieLineItemHydrator;
        $this->shippingLineItemBuilder = $shippingLineItemBuilder;
    }

    /**
     * @return array<mixed>
     */
    public function buildLineItemPayload(OrderEntity $order, string $currencyISO, MollieSettingStruct $settings, bool $isVerticalTaxCalculation): array
    {
        $fixRoundingDifferences = $settings->isFixRoundingDiffEnabled();
        $fixRoundingTitle = $settings->getFixRoundingDiffName();
        $fixRoundingSKU = $settings->getFixRoundingDiffSKU();
        $taxStatus = (string) $order->getTaxStatus();
        $mollieOrderLines = $this->buildLineItems($taxStatus, $order->getNestedLineItems(), $isVerticalTaxCalculation);

        $deliveries = $order->getDeliveries();

        if ($deliveries instanceof OrderDeliveryCollection) {
            $shippingLineItems = $this->shippingLineItemBuilder->buildShippingLineItems(
                $taxStatus,
                $deliveries,
                $isVerticalTaxCalculation
            );

            foreach ($shippingLineItems as $shipping) {
                $mollieOrderLines->add($shipping);
            }
        }

        // if we should automatically fix any rounding issues
        // then proceed with this. It will make sure that a separate line item
        // is created so that the sum of line item values matches the order total value.
        if ($fixRoundingDifferences) {
            $mollieOrderLines = $this->roundingDiffFixer->fixAmountDiff(
                $order->getAmountTotal(),
                $mollieOrderLines,
                $fixRoundingTitle,
                $fixRoundingSKU
            );
        }

        return $this->mollieLineItemHydrator->hydrate($mollieOrderLines, $currencyISO);
    }

    public function buildLineItems(string $taxStatus, ?OrderLineItemCollection $lineItems, bool $isVerticalTaxCalculation): MollieLineItemCollection
    {
        $lines = new MollieLineItemCollection();

        if (! $lineItems instanceof OrderLineItemCollection || $lineItems->count() === 0) {
            return $lines;
        }

        $lineItems = $this->getLineItemsFlat($lineItems);

        $ignoreTypes = [
            self::LINE_ITEM_REPERTUS_SET,
            self::LINE_ITEM_SKWEB_SET,
            self::LINE_ITEM_TYPE_CUSTOM_PRODUCTS,
        ];

        foreach ($lineItems as $item) {
            if (in_array($item->getType(), $ignoreTypes, true)) {
                continue;
            }

            $this->orderLineItemValidator->validate($item);
            $extraData = $this->lineItemDataExtractor->extractExtraData($item);
            $itemPrice = $item->getPrice();
            $itemPriceDefinition = $item->getPriceDefinition();

            if ($itemPriceDefinition instanceof AbsolutePriceDefinition) {
                $item->setQuantity(1);
            }

            if (! $itemPrice instanceof CalculatedPrice) {
                throw new MissingPriceLineItemException((string) $item->getProductId());
            }

            $price = $this->priceCalculator->calculateLineItemPrice(
                $itemPrice,
                $item->getTotalPrice(),
                $taxStatus,
                $isVerticalTaxCalculation
            );

            $mollieLineItem = new MollieLineItem(
                (string) $this->getLineItemType($item),
                $item->getLabel(),
                $item->getQuantity(),
                $price,
                $item->getId(),
                $extraData->getSku(),
                (string) $extraData->getImageUrl(),
                (string) $extraData->getProductUrl()
            );

            $lines->add($mollieLineItem);
        }

        return $lines;
    }

    public function getLineItemPromotionType(): string
    {
        if (defined('Shopware\Core\Checkout\Cart\LineItem::PROMOTION_LINE_ITEM_TYPE')) {
            return LineItem::PROMOTION_LINE_ITEM_TYPE;
        }

        return 'promotion';
    }

    /**
     * @return OrderLineItemEntity[]
     */
    private function getLineItemsFlat(?OrderLineItemCollection $lineItems): array
    {
        $flat = [];
        if (! $lineItems) {
            return $flat;
        }

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() === self::LINE_ITEM_DREISEC_SET) {
                foreach ($this->getLineItemsFlat($lineItem->getChildren()) as $nest) {
                    if (stristr((string) $nest->getType(), self::LINE_ITEM_DREISEC_SET) !== false) {
                        $flat[] = $nest;
                    }
                }
                continue;
            }
            $flat[] = $lineItem;

            foreach ($this->getLineItemsFlat($lineItem->getChildren()) as $nest) {
                $flat[] = $nest;
            }
        }

        return $flat;
    }

    /**
     * Return the type of the line item.
     */
    private function getLineItemType(OrderLineItemEntity $item): string
    {
        if ($item->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
            return OrderLineType::TYPE_PHYSICAL;
        }

        if ($item->getType() === LineItem::CREDIT_LINE_ITEM_TYPE) {
            return OrderLineType::TYPE_STORE_CREDIT;
        }

        if ($item->getType() === $this->getLineItemPromotionType() || $item->getTotalPrice() < 0) {
            return OrderLineType::TYPE_DISCOUNT;
        }

        if ($item->getType() === self::LINE_ITEM_TYPE_CUSTOM_PRODUCTS) {
            return OrderLineType::TYPE_PHYSICAL;
        }

        return OrderLineType::TYPE_DIGITAL;
    }
}
