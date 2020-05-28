<?php

namespace Kiener\MolliePayments\Service;

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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderService
{
    public const ORDER_LINE_ITEM_ID = 'orderLineItemId';

    /** @var EntityRepository */
    protected $orderRepository;

    /** @var EntityRepository */
    protected $orderLineItemRepository;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(
        EntityRepository $orderRepository,
        EntityRepository $orderLineItemRepository,
        LoggerInterface $logger
    )
    {
        $this->orderRepository = $orderRepository;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->logger = $logger;
    }

    /**
     * Returns the order repository.
     *
     * @return EntityRepository
     */
    public function getOrderRepository()
    {
        return $this->orderRepository;
    }

    /**
     * Returns the order line item repository.
     *
     * @return EntityRepository
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
    public function getOrder(string $orderId, Context $context) : ?OrderEntity
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
            $criteria->addAssociation('deliveries');
            $criteria->addAssociation('deliveries.shippingOrderAddress');

            /** @var OrderEntity $order */
            $order = $this->orderRepository->search($criteria, $context)->first();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        return $order;
    }

    /**
     * Return an array of order lines.
     *
     * @param OrderEntity $order
     * @return array
     */
    public function getOrderLinesArray(OrderEntity $order): array
    {
        // Variables
        $lines = [];
        $lineItems = $order->getLineItems();

        if ($lineItems === null || $lineItems->count() === 0) {
            return $lines;
        }

        // Get currency code
        $currency = $order->getCurrency();
        $currencyCode = $currency !== null ? $currency->getIsoCode() : 'EUR';

        foreach ($lineItems as $item) {
            // Get tax
            $itemTax = null;

            if ($item->getPrice() !== null &&
                $item->getPrice()->getCalculatedTaxes() !== null) {
                $itemTax = $this->getLineItemTax($item->getPrice()->getCalculatedTaxes());
            }

            // Get VAT rate and amount
            $vatRate = $itemTax !== null ? $itemTax->getTaxRate() : 0.0;
            $vatAmount = $itemTax !== null ? $itemTax->getTax() : null;

            if ($vatAmount === null && $vatRate > 0) {
                $vatAmount = $item->getTotalPrice() * ($vatRate / ($vatRate + 100));
            }

            // Remove VAT if the order is tax free
            if ($order->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
                $vatRate = 0.0;
                $vatAmount = 0.0;
            }

            // Get the SKU
            $sku = null;

            if ($item->getProduct() !== null) {
                $sku = $item->getProduct()->getProductNumber();
            }

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
                'type' =>  $this->getLineItemType($item),
                'name' => $item->getLabel(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $this->getPriceArray($currencyCode, $item->getUnitPrice()),
                'totalAmount' => $this->getPriceArray($currencyCode, $item->getTotalPrice()),
                'vatRate' => number_format($vatRate, 2, '.', ''),
                'vatAmount' => $this->getPriceArray($currencyCode, $vatAmount),
                'sku' => $sku,
                'imageUrl' => $imageUrl,
                'productUrl' => $productUrl,
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
    public function getShippingItemArray(OrderEntity $order) : array
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

        // Get VAT rate and amount
        $vatRate = $shippingTax !== null ? $shippingTax->getTaxRate() : 0.0;
        $vatAmount = $vatAmount = $shippingTax !== null ? $shippingTax->getTax() : null;

        if ($vatAmount === null && $vatRate > 0) {
            $vatAmount = $shipping->getTotalPrice() * ($vatRate / ($vatRate + 100));
        }

        // Remove VAT if the order is tax free
        if ($order->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
            $vatRate = 0.0;
            $vatAmount = 0.0;
        }

        // Build the order line array
        $line = [
            'type' =>  OrderLineType::TYPE_SHIPPING_FEE,
            'name' => 'Shipping',
            'quantity' => $shipping->getQuantity(),
            'unitPrice' => $this->getPriceArray($currencyCode, $shipping->getUnitPrice()),
            'totalAmount' => $this->getPriceArray($currencyCode, $shipping->getTotalPrice()),
            'vatRate' => number_format($vatRate, 2, '.', ''),
            'vatAmount' => $this->getPriceArray($currencyCode, $vatAmount),
            'sku' => null,
            'imageUrl' => null,
            'productUrl' => null,
        ];

        return $line;
    }

    /**
     * Return an array of price data; currency and value.
     * @param string $currency
     * @param float|null $price
     * @param int $decimals
     * @return array
     */
    public function getPriceArray(string $currency, ?float $price = null, int $decimals = 2) : array
    {
        if ($price === null) {
            $price = 0.0;
        }

        return [
            'currency' => $currency,
            'value' => number_format($price, $decimals, '.', '')
        ];
    }

    /**
     * Return the type of the line item.
     *
     * @param OrderLineItemEntity $item
     * @return string|null
     */
    public function getLineItemType(OrderLineItemEntity $item) : ?string
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

        return OrderLineType::TYPE_DIGITAL;
    }

    /**
     * Return a calculated tax struct for a line item.
     *
     * @param CalculatedTaxCollection $taxCollection
     * @return CalculatedTax|null
     */
    public function getLineItemTax(CalculatedTaxCollection $taxCollection)
    {
        $tax = null;

        if ($taxCollection->count() > 0) {
            /** @var CalculatedTax $tax */
            $tax = $taxCollection->first();
        }

        return $tax;
    }
}