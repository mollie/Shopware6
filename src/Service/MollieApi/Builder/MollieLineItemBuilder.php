<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi\Builder;

use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGatewayInterface;
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
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

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

    /**
     * @var CompatibilityGatewayInterface
     */
    private $compatibilityGateway;

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

    /**
     * @param IsOrderLineItemValid $orderLineItemValidator
     * @param PriceCalculator $priceCalculator
     * @param LineItemDataExtractor $lineItemDataExtractor
     * @param CompatibilityGatewayInterface $compatibilityGateway
     * @param RoundingDifferenceFixer $orderAmountFixer
     * @param MollieLineItemHydrator $mollieLineItemHydrator
     * @param MollieShippingLineItemBuilder $shippingLineItemBuilder
     */
    public function __construct(IsOrderLineItemValid $orderLineItemValidator, PriceCalculator $priceCalculator, LineItemDataExtractor $lineItemDataExtractor, CompatibilityGatewayInterface $compatibilityGateway, RoundingDifferenceFixer $orderAmountFixer, MollieLineItemHydrator $mollieLineItemHydrator, MollieShippingLineItemBuilder $shippingLineItemBuilder)
    {
        $this->orderLineItemValidator = $orderLineItemValidator;
        $this->priceCalculator = $priceCalculator;
        $this->lineItemDataExtractor = $lineItemDataExtractor;
        $this->compatibilityGateway = $compatibilityGateway;
        $this->roundingDiffFixer = $orderAmountFixer;
        $this->mollieLineItemHydrator = $mollieLineItemHydrator;
        $this->shippingLineItemBuilder = $shippingLineItemBuilder;
    }


    /**
     * @param OrderEntity $order
     * @param string $currencyISO
     * @param MollieSettingStruct $settings
     * @param bool $isVerticalTaxCalculation
     * @return array<mixed>
     */
    public function buildLineItemPayload(OrderEntity $order, string $currencyISO, MollieSettingStruct $settings, bool $isVerticalTaxCalculation): array
    {
        $fixRoundingDifferences = $settings->isFixRoundingDiffEnabled();
        $fixRoundingTitle = $settings->getFixRoundingDiffName();
        $fixRoundingSKU = $settings->getFixRoundingDiffSKU();


        $mollieOrderLines = $this->buildLineItems($order->getTaxStatus(), $order->getNestedLineItems(), $isVerticalTaxCalculation);

        $deliveries = $order->getDeliveries();

        if ($deliveries instanceof OrderDeliveryCollection) {
            $shippingLineItems = $this->shippingLineItemBuilder->buildShippingLineItems(
                $order->getTaxStatus(),
                $deliveries,
                $isVerticalTaxCalculation
            );

            foreach ($shippingLineItems as $shipping) {
                $mollieOrderLines->add($shipping);
            }
        }

        # if we should automatically fix any rounding issues
        # then proceed with this. It will make sure that a separate line item
        # is created so that the sum of line item values matches the order total value.
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


    /**
     * @param string $taxStatus
     * @param null|OrderLineItemCollection $lineItems
     * @param bool $isVerticalTaxCalculation
     * @return MollieLineItemCollection
     */
    public function buildLineItems(string $taxStatus, ?OrderLineItemCollection $lineItems, bool $isVerticalTaxCalculation): MollieLineItemCollection
    {
        $lines = new MollieLineItemCollection();

        if (!$lineItems instanceof OrderLineItemCollection || $lineItems->count() === 0) {
            return $lines;
        }


        foreach ($lineItems as $item) {
            $this->orderLineItemValidator->validate($item);
            $extraData = $this->lineItemDataExtractor->extractExtraData($item);
            $itemPrice = $item->getPrice();

            if (!$itemPrice instanceof CalculatedPrice) {
                throw new MissingPriceLineItemException((string)$item->getProductId());
            }

            $price = $this->priceCalculator->calculateLineItemPrice(
                $itemPrice,
                $item->getTotalPrice(),
                $taxStatus,
                $isVerticalTaxCalculation
            );

            $mollieLineItem = new MollieLineItem(
                (string)$this->getLineItemType($item),
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
     * @return string
     */
    private function getLineItemType(OrderLineItemEntity $item): string
    {
        if ($item->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
            return OrderLineType::TYPE_PHYSICAL;
        }

        if ($item->getType() === LineItem::CREDIT_LINE_ITEM_TYPE) {
            return OrderLineType::TYPE_STORE_CREDIT;
        }

        if ($item->getType() === $this->compatibilityGateway->getLineItemPromotionType() || $item->getTotalPrice() < 0) {
            return OrderLineType::TYPE_DISCOUNT;
        }

        if ($item->getType() === self::LINE_ITEM_TYPE_CUSTOM_PRODUCTS) {
            return OrderLineType::TYPE_PHYSICAL;
        }

        return OrderLineType::TYPE_DIGITAL;
    }
}
