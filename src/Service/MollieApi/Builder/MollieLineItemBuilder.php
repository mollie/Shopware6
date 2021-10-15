<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi\Builder;


use Kiener\MolliePayments\Compatibility\CompatibilityFactory;
use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGatewayInterface;
use Kiener\MolliePayments\Exception\MissingPriceLineItemException;
use Kiener\MolliePayments\Service\MollieApi\LineItemDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\PriceCalculator;
use Kiener\MolliePayments\Validator\IsOrderLineItemValid;
use Mollie\Api\Types\OrderLineType;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

class MollieLineItemBuilder
{
    public const LINE_ITEM_TYPE_CUSTOM_PRODUCTS = 'customized-products';

    /**
     * @var MollieOrderPriceBuilder
     */
    private $priceHydrator;
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
     * @param MollieOrderPriceBuilder $priceHydrator
     * @param IsOrderLineItemValid $orderLineItemValidator
     * @param PriceCalculator $priceCalculator
     * @param LineItemDataExtractor $lineItemDataExtractor
     * @param CompatibilityGatewayInterface $compatibilityGateway
     */
    public function __construct(MollieOrderPriceBuilder $priceHydrator, IsOrderLineItemValid $orderLineItemValidator, PriceCalculator $priceCalculator, LineItemDataExtractor $lineItemDataExtractor, CompatibilityGatewayInterface $compatibilityGateway)
    {
        $this->priceHydrator = $priceHydrator;
        $this->orderLineItemValidator = $orderLineItemValidator;
        $this->priceCalculator = $priceCalculator;
        $this->lineItemDataExtractor = $lineItemDataExtractor;
        $this->compatibilityGateway = $compatibilityGateway;
    }

    public function buildLineItems(string $taxStatus, ?OrderLineItemCollection $lineItems, ?CurrencyEntity $currency): array
    {
        $lines = [];

        if (!$lineItems instanceof OrderLineItemCollection || $lineItems->count() === 0) {
            return $lines;
        }

        $currencyCode = MollieOrderPriceBuilder::MOLLIE_FALLBACK_CURRENCY_CODE;
        if ($currency instanceof CurrencyEntity) {
            $currencyCode = $currency->getIsoCode();
        }

        /** @var OrderLineItemEntity $item */
        foreach ($lineItems as $item) {
            $this->orderLineItemValidator->validate($item);
            $extraData = $this->lineItemDataExtractor->extractExtraData($item);
            $itemPrice = $item->getPrice();

            if (!$itemPrice instanceof CalculatedPrice) {
                throw new MissingPriceLineItemException($item->getProductId());
            }

            $prices = $this->priceCalculator->calculateLineItemPrice($item->getPrice(), $item->getTotalPrice(), $taxStatus);

            $lines[] = [
                'type' => $this->getLineItemType($item),
                'name' => $item->getLabel(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $this->priceHydrator->build($prices->getUnitPrice(), $currencyCode),
                'totalAmount' => $this->priceHydrator->build($prices->getTotalAmount(), $currencyCode),
                'vatRate' => number_format($prices->getVatRate(), MollieOrderPriceBuilder::MOLLIE_PRICE_PRECISION, '.', ''),
                'vatAmount' => $this->priceHydrator->build($prices->getVatAmount(), $currencyCode),
                'sku' => $extraData->getSku(),
                'imageUrl' => urlencode((string)$extraData->getImageUrl()),
                'productUrl' => urlencode((string)$extraData->getProductUrl()),
                'metadata' => [
                    'orderLineItemId' => $item->getId(),
                ],
            ];
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

        if ($item->getType() === $this->compatibilityGateway->getLineItemPromotionType() || $item->getTotalPrice() < 0) {
            return OrderLineType::TYPE_DISCOUNT;
        }

        if ($item->getType() === self::LINE_ITEM_TYPE_CUSTOM_PRODUCTS) {
            return OrderLineType::TYPE_PHYSICAL;
        }

        return OrderLineType::TYPE_DIGITAL;
    }
}
