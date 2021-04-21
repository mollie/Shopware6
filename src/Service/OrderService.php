<?php

namespace Kiener\MolliePayments\Service;

use Exception;
use Kiener\MolliePayments\Exception\MissingPriceLineItemException;
use Kiener\MolliePayments\Validator\OrderLineItemValidator;
use Mollie\Api\Types\OrderLineType;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderService
{
    public const ORDER_LINE_ITEM_ID = 'orderLineItemId';

    private const LINE_ITEM_TYPE_CUSTOM_PRODUCTS = 'customized-products';

    private const TAX_ARRAY_KEY_TAX = 'tax';
    private const TAX_ARRAY_KEY_TAX_RATE = 'taxRate';
    private const TAX_ARRAY_KEY_PRICE = 'price';

    private const MOLLIE_PRICE_PRECISION = 2;

    /** @var EntityRepositoryInterface */
    protected $orderRepository;

    /** @var EntityRepositoryInterface */
    protected $orderLineItemRepository;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * @var OrderLineItemValidator
     */
    private $orderLineItemValidator;

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderLineItemRepository,
        LoggerInterface $logger,
        OrderLineItemValidator $orderLineItemValidator
    )
    {
        $this->orderRepository = $orderRepository;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->logger = $logger;
        $this->orderLineItemValidator = $orderLineItemValidator;
    }

    /**
     * Returns the order repository.
     *
     * @return EntityRepositoryInterface
     */
    public function getOrderRepository()
    {
        return $this->orderRepository;
    }

    /**
     * Returns the order line item repository.
     *
     * @return EntityRepositoryInterface
     */
    public function getOrderLineItemRepository()
    {
        return $this->orderLineItemRepository;
    }

    /**
     * Return an order entity, enriched with associations.
     *
     * @param string $orderId
     * @param Context $context
     * @return OrderEntity|null
     */
    public function getOrder(string $orderId, Context $context): ?OrderEntity
    {
        $order = null;

        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $orderId));
            $criteria->addAssociation('currency');
            $criteria->addAssociation('addresses');
            $criteria->addAssociation('language');
            $criteria->addAssociation('language.locale');
            $criteria->addAssociation('lineItems');
            $criteria->addAssociation('lineItems.product');
            $criteria->addAssociation('lineItems.product.media');
            $criteria->addAssociation('deliveries');
            $criteria->addAssociation('deliveries.shippingOrderAddress');
            $criteria->addAssociation('transactions');
            $criteria->addAssociation('transactions.paymentMethod');

            /** @var OrderEntity $order */
            $order = $this->orderRepository->search($criteria, $context)->first();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        return $order;
    }

    /**
     * Return an array of order lines.
     *
     * @param OrderEntity $order
     * @return array
     * @throws MissingPriceLineItemException
     */
    public function getOrderLinesArray(OrderEntity $order): array
    {
        // Variables
        $lines = [];
        $lineItems = $order->getNestedLineItems();

        if ($lineItems === null || $lineItems->count() === 0) {
            return $lines;
        }

        // Get currency code
        $currency = $order->getCurrency();
        $currencyCode = $currency !== null ? $currency->getIsoCode() : 'EUR';

        /** @var OrderLineItemEntity $item */
        foreach ($lineItems as $item) {
            // Get the SKU
            $sku = null;

            if ($item->getProduct() !== null) {
                $sku = $item->getProduct()->getProductNumber();
            }

            $molliePreparedApiPrices = $this->calculateLineItemPriceData($item, $order->getTaxStatus(), $currencyCode);

            // Get the image
            $imageUrl = null;

            if (
                $item->getProduct() !== null
                && $item->getProduct()->getMedia() !== null
                && $item->getProduct()->getMedia()->count()
                && $item->getProduct()->getMedia()->first() !== null
                && $item->getProduct()->getMedia()->first()->getMedia()
            ) {
                $imageUrl = $item->getProduct()->getMedia()->first()->getMedia()->getUrl();
            }

            // Get the product URL
            $productUrl = null;

            if (
                $item->getProduct() !== null
                && $item->getProduct()->getSeoUrls() !== null
                && $item->getProduct()->getSeoUrls()->count()
                && $item->getProduct()->getSeoUrls()->first() !== null
            ) {
                $productUrl = $item->getProduct()->getSeoUrls()->first()->getUrl();
            }

            // Build the order lines array
            $lines[] = [
                'type' => $this->getLineItemType($item),
                'name' => $item->getLabel(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $molliePreparedApiPrices['unitPrice'],
                'totalAmount' => $molliePreparedApiPrices['totalAmount'],
                'vatRate' => $molliePreparedApiPrices['vatRate'],
                'vatAmount' => $molliePreparedApiPrices['vatAmount'],
                'sku' => $sku,
                'imageUrl' => urlencode($imageUrl),
                'productUrl' => urlencode($productUrl),
                'metadata' => [
                    self::ORDER_LINE_ITEM_ID => $item->getId(),
                ],
            ];
        }

        $lines[] = $this->getShippingItemArray($order);

        return $lines;
    }

    /**
     * Return an array of shipping data.
     *
     * @param OrderEntity $order
     * @return array
     */
    public function getShippingItemArray(OrderEntity $order): array
    {
        // Variables
        $line = [];
        $shipping = $order->getShippingCosts();

        if ($shipping === null) {
            return $line;
        }

        // Get currency code
        $currency = $order->getCurrency();
        $currencyCode = $currency !== null ? $currency->getIsoCode() : 'EUR';

        // Get shipping tax
        $shippingTax = null;

        if ($shipping->getCalculatedTaxes() !== null) {
            $shippingTax = $this->getLineItemTax($shipping->getCalculatedTaxes());
        }

        // Get VAT rate
        $vatRate = $shippingTax !== null ? $shippingTax->getTaxRate() : 0.0;

        // Remove VAT if the order is tax free
        if ($order->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
            $vatRate = 0.0;
        }

        // Get the prices
        $unitPrice = $shipping->getUnitPrice();
        $totalAmount = $totalAmountTemp = $shipping->getTotalPrice();
        $vatAmount = $shipping->getCalculatedTaxes()->getAmount();
        $vatRateTemp = $vatRate;

        // Add tax when order is net
        if ($order->getTaxStatus() === CartPrice::TAX_STATE_NET) {
            $unitPrice *= ((100 + $vatRate) / 100);
            $totalAmount += $vatAmount;
            //Check if Vat is still correct by recalculating different Vat users
            $multiShipVatAmount = ($totalAmountTemp / 100) * $vatRateTemp;
            //if Vat Amount is off because of multiple Vat's reset it.
            if ($multiShipVatAmount !== $vatRate) {
                $vatAmount = $multiShipVatAmount;
            }
        }

        // Build the order line array
        $shippingLine = [
            'type' => OrderLineType::TYPE_SHIPPING_FEE,
            'name' => 'Shipping',
            'quantity' => $shipping->getQuantity(),
            'unitPrice' => $this->getPriceArray($currencyCode, $unitPrice),
            'totalAmount' => $this->getPriceArray($currencyCode, $totalAmount),
            'vatRate' => number_format($vatRate, 2, '.', ''),
            'vatAmount' => $this->getPriceArray($currencyCode, $vatAmount),
            'sku' => null,
//            'imageUrl' => null,
//            'productUrl' => null,
        ];

        return $shippingLine;
    }

    /**
     * returns an array of totalPrice, unitPrice and vatAmount that is calculated like mollie api does
     * @param OrderLineItemEntity $item
     * @param string $orderTaxType
     * @param string $currencyCode
     * @return array
     */
    public function calculateLineItemPriceData(OrderLineItemEntity $item, string $orderTaxType, string $currencyCode): array
    {
        $this->orderLineItemValidator->validate($item);

        $price = $item->getPrice();
        $taxCollection = $price->getCalculatedTaxes();

        $vatRate = 0.0;
        $itemTax = $this->getLineItemTax($taxCollection);
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

        return [
            'unitPrice' => $this->getPriceArray($currencyCode, $unitPrice),
            'totalAmount' => $this->getPriceArray($currencyCode, $roundedLineItemTotalPrice),
            'vatAmount' => $this->getPriceArray($currencyCode, $roundedVatAmount),
            'vatRate' => number_format($roundedVatRate, self::MOLLIE_PRICE_PRECISION, '.', '')
        ];
    }

    /**
     * Return an array of price data; currency and value.
     * @param string $currency
     * @param float|null $price
     * @param int $decimals
     * @return array
     */
    public function getPriceArray(string $currency, ?float $price = null): array
    {
        if ($price === null) {
            $price = 0.0;
        }

        return [
            'currency' => $currency,
            'value' => number_format(round($price, self::MOLLIE_PRICE_PRECISION), self::MOLLIE_PRICE_PRECISION, '.', '')
        ];
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

        if ($item->getType() === static::LINE_ITEM_TYPE_CUSTOM_PRODUCTS) {
            return OrderLineType::TYPE_PHYSICAL;
        }

        return OrderLineType::TYPE_DIGITAL;
    }

    /**
     * Return a calculated tax struct for a line item.
     *
     * @param CalculatedTaxCollection $taxCollection
     * @return CalculatedTax|null
     */
    public function getLineItemTax(CalculatedTaxCollection $taxCollection): ?CalculatedTax
    {
        if ($taxCollection->count() === 0) {
            return null;
        } elseif ($taxCollection->count() === 1) {
            return $taxCollection->first();
        } else {
            $tax = [
                self::TAX_ARRAY_KEY_TAX => 0,
                self::TAX_ARRAY_KEY_TAX_RATE => 0,
                self::TAX_ARRAY_KEY_PRICE => 0,
            ];

            $taxCollection->map(static function (CalculatedTax $calculatedTax) use (&$tax) {
                $tax[self::TAX_ARRAY_KEY_TAX] += $calculatedTax->getTax();
                $tax[self::TAX_ARRAY_KEY_PRICE] += $calculatedTax->getPrice();
            });

            if ($tax[self::TAX_ARRAY_KEY_PRICE] !== $tax[self::TAX_ARRAY_KEY_TAX]) {
                $tax[self::TAX_ARRAY_KEY_TAX_RATE] = $tax[self::TAX_ARRAY_KEY_TAX] / ($tax[self::TAX_ARRAY_KEY_PRICE] - $tax[self::TAX_ARRAY_KEY_TAX]);
            }

            return new CalculatedTax(
                $tax[self::TAX_ARRAY_KEY_TAX],
                round($tax[self::TAX_ARRAY_KEY_TAX_RATE], 4) * 100,
                $tax[self::TAX_ARRAY_KEY_PRICE]
            );
        }
    }
}
