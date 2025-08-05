<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi\Builder;

use Kiener\MolliePayments\Exception\MissingPriceLineItemException;
use Kiener\MolliePayments\Hydrator\MollieLineItemHydrator;
use Kiener\MolliePayments\Service\MollieApi\Fixer\RoundingDifferenceFixer;
use Kiener\MolliePayments\Service\MollieApi\LineItemDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\PriceCalculator;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Struct\LineItemPriceStruct;
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

        $mollieOrderLines = $this->addGiftCardLineItems($order, $mollieOrderLines);

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

        $customizedProducts = $lineItems->filterByType(self::LINE_ITEM_TYPE_CUSTOM_PRODUCTS);

        foreach ($customizedProducts as $customizedProduct) {
            $productChildren = $customizedProduct->getChildren();
            if ($productChildren === null) {
                continue;
            }
            $options = $productChildren->filterByType(self::LINE_ITEM_TYPE_CUSTOM_PRODUCTS_OPTIONS);
            foreach ($options as $option) {
                $optionValues = $option->getChildren();
                if ($optionValues !== null) {
                    foreach ($optionValues as $optionValue) {
                        if ($optionValue->getPrice() !== null && $optionValue->getPrice()->getTotalPrice() > 0) {
                            $lineItems->add($optionValue);
                        }
                    }
                }
                if ($option->getPrice() !== null && $option->getPrice()->getTotalPrice() > 0) {
                    $lineItems->add($option);
                }
            }
        }

        foreach ($lineItems as $item) {
            /* Filter out the product from customized products plugin */
            if ($item->getType() === self::LINE_ITEM_TYPE_CUSTOM_PRODUCTS) {
                $lineItemChildren = $item->getChildren();

                if ($lineItemChildren instanceof OrderLineItemCollection && $lineItemChildren->count() > 0) {
                    $filteredItems = $lineItemChildren->filter(function (OrderLineItemEntity $lineItemEntity) {
                        return $lineItemEntity->getType() !== self::LINE_ITEM_TYPE_CUSTOM_PRODUCTS_OPTIONS;
                    });

                    if ($filteredItems->count() === 1) {
                        /** @var OrderLineItemEntity $item */
                        $item = $filteredItems->first();
                    }
                }
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
     * apply giftcards to the order from the voucher plugin https://store.shopware.com/de/laene61720950437m/gutscheine.html
     */
    private function addGiftCardLineItems(OrderEntity $order, MollieLineItemCollection $mollieOrderLines): MollieLineItemCollection
    {
        $orderCustomFields = $order->getCustomFields();
        if (! isset($orderCustomFields['lae-giftcards'])) {
            return $mollieOrderLines;
        }
        $giftCards = $orderCustomFields['lae-giftcards'];
        foreach ($giftCards as $giftCard) {
            $cardAmount = $giftCard['appliedAmount'] * -1;
            $priceStruct = new LineItemPriceStruct($cardAmount, $cardAmount, 0, 0);
            $mollieLineItem = new MollieLineItem(
                OrderLineType::TYPE_GIFT_CARD,
                sprintf('Giftcard %s', $giftCard['name']),
                1,
                $priceStruct,
                $giftCard['giftcardId'],
                $giftCard['code'],
                '',
                ''
            );
            $mollieOrderLines->add($mollieLineItem);
        }

        return $mollieOrderLines;
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
