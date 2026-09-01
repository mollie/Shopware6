<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund;

use Mollie\Shopware\Component\Mollie\CreateOrderRefund;
use Mollie\Shopware\Component\Mollie\CreatePaymentRefund;
use Mollie\Shopware\Component\Mollie\CreateRefund;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as ShopwareLineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RefundBuilder implements RefundBuilderInterface
{
    public function __construct(
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        private RefundableTotalCalculator $refundableTotalCalculator,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<array{id: string, quantity: int, amount: float, resetStock: int}> $requestItems
     */
    public function build(Payment $payment, OrderEntity $order, array $requestItems, string $description, ?float $requestAmount = null): CreateRefund
    {
        $salesChannelId = (string) $order->getSalesChannelId();
        $orderNumber = (string) $order->getOrderNumber();

        $currency = $order->getCurrency();
        if (! $currency instanceof CurrencyEntity) {
            throw new \RuntimeException(sprintf('No currency found for order "%s"', $order->getId()));
        }

        $taxStatus = (string) $order->getTaxStatus();
        $allOrderLineItems = $order->getLineItems() ?? new OrderLineItemCollection();
        $shippingDiscountLabel = LineItem::resolveDeliveryDiscountLabel($allOrderLineItems);
        $orderLineItems = $allOrderLineItems
            ->filter(function (OrderLineItemEntity $item): bool {
                return $item->getType() !== ShopwareLineItem::CREDIT_LINE_ITEM_TYPE
                    && ! LineItem::isDeliveryDiscountPlaceholder($item);
            })
        ;
        $orderDeliveries = $order->getDeliveries() ?? new OrderDeliveryCollection();

        $hasRequestedItems = count($requestItems) > 0;
        $isFullRefund = ($requestAmount === null && ! $hasRequestedItems);

        $lineItems = new LineItemCollection();
        $mollieLines = null;
        $mollieRefundable = null;

        if ($payment->getOrderId() !== null) {
            $mollieOrder = $this->mollieGateway->getOrder($payment->getOrderId(), $salesChannelId);
            $mollieLines = $mollieOrder->getLines();
            $existingRefunds = $mollieOrder->getRefunds();
        } else {
            $molliePayment = $this->mollieGateway->getPayment($payment->getId(), $orderNumber, $salesChannelId);
            $existingRefunds = $molliePayment->getRefunds();
            $mollieRefundable = $molliePayment->getRefundableAmount();
        }

        $alreadyRefunded = $existingRefunds->getSumRefunded() + $existingRefunds->getSumPending();

        $amount = $requestAmount ?? $order->getAmountTotal();

        if ($isFullRefund && $alreadyRefunded <= 0.0) {
            $lineItems = $this->buildItemsFromOrder($orderLineItems, $orderDeliveries, $taxStatus, $currency, $mollieLines, $shippingDiscountLabel);
        }

        if ($hasRequestedItems) {
            $lineItems = $this->buildFromRequestItems($requestItems, $orderLineItems, $orderDeliveries, $taxStatus, $currency, $mollieLines, $shippingDiscountLabel);
            // Only override amount if no explicit amount was requested
            if ($requestAmount === null) {
                $amount = $lineItems->getTotal();
            }
        }

        $baseTotal = $this->refundableTotalCalculator->calculate($order);
        $maxRefundable = max(0.0, $baseTotal - $alreadyRefunded);

        if ($mollieRefundable !== null) {
            $maxRefundable = min($maxRefundable, max(0.0, $mollieRefundable));
        }

        $amount = round(min($amount, $maxRefundable), Mollie::ROUNDING_PRECISION);

        $money = new Money($amount, $currency->getIsoCode());

        $refundLines = $mollieLines !== null ? $this->resolveMollieLineIds($lineItems, $mollieLines, $orderNumber) : $lineItems;

        // Use order-based refund with line items only when no custom amount is requested;
        // a custom amount requires payment-level refund so Mollie honors the amount.
        if ($payment->getOrderId() !== null && $refundLines->count() > 0 && $requestAmount === null) {
            $createRefund = new CreateOrderRefund($payment->getOrderId(), $refundLines);
            $createRefund->setDescription($description);
        } else {
            $createRefund = new CreatePaymentRefund($payment->getId(), $money, $description);
        }

        $this->logger->debug('Refund payload built', [
            'orderNumber' => $orderNumber,
            'amount' => $amount,
            'maxRefundable' => $maxRefundable,
            'mollieRefundable' => $mollieRefundable,
            'lineCount' => $lineItems->count(),
            'isOrderRefund' => $createRefund instanceof CreateOrderRefund,
        ]);

        return $createRefund;
    }

    /**
     * The Mollie line id comes from the "order_line_id" custom field, which older plugin versions
     * only wrote for line items and never for deliveries. Since every refund line carries its
     * Shopware id, fall back to the live Mollie order for the missing ones - otherwise the shipping
     * line of such an order is sent without an id and Mollie rejects the whole refund with
     * "Line x contains invalid data. The 'id' field is missing".
     */
    private function resolveMollieLineIds(LineItemCollection $lineItems, LineItemCollection $mollieLines, string $orderNumber): LineItemCollection
    {
        foreach ($lineItems as $lineItem) {
            if ($lineItem->getId() !== '') {
                continue;
            }

            $mollieLineId = (string) $mollieLines->findByShopwareId($lineItem->getShopwareLineItemId())?->getId();
            if ($mollieLineId === '') {
                continue;
            }

            $lineItem->setId($mollieLineId);
        }

        $refundLines = $lineItems->filter(function (LineItem $lineItem): bool {
            return $lineItem->getId() !== '';
        });

        if ($refundLines->count() !== $lineItems->count()) {
            $this->logger->warning('Refund lines without a Mollie line id are skipped', [
                'orderNumber' => $orderNumber,
                'lineCount' => $lineItems->count(),
                'skippedCount' => $lineItems->count() - $refundLines->count(),
            ]);
        }

        return $refundLines;
    }

    private function buildItemsFromOrder(OrderLineItemCollection $orderLineItems, OrderDeliveryCollection $orderDeliveries, string $taxStatus, CurrencyEntity $currency, ?LineItemCollection $mollieLines, ?string $shippingDiscountLabel = null): LineItemCollection
    {
        $collection = new LineItemCollection();

        foreach ($orderLineItems as $orderLineItem) {
            $quantity = $orderLineItem->getQuantity();

            if ($mollieLines !== null) {
                $mollieLine = $mollieLines->findByShopwareId($orderLineItem->getId());
                $quantity = $mollieLine?->getRefundableQuantity() ?? 0;
            }

            if ($quantity === 0) {
                continue;
            }

            $refundLine = LineItem::fromOrderLine($orderLineItem, $currency, $taxStatus);
            $refundLine->setQuantity($quantity);

            if ($quantity < $orderLineItem->getQuantity()) {
                $reducedAmount = new Money(
                    (float) $refundLine->getUnitPrice()->getValue() * $quantity,
                    $currency->getIsoCode()
                );
                $refundLine->setAmount($reducedAmount);
            }

            $collection->add($refundLine);
        }

        foreach ($orderDeliveries as $delivery) {
            $quantity = 1;

            if ($mollieLines !== null) {
                $mollieDeliveryLine = $mollieLines->findByShopwareId($delivery->getId());
                $quantity = $mollieDeliveryLine?->getRefundableQuantity() ?? 0;
            }

            if ($quantity === 0) {
                continue;
            }

            $descriptionOverride = $delivery->getShippingCosts()->getTotalPrice() < 0 ? $shippingDiscountLabel : null;
            $collection->add(LineItem::fromDelivery($delivery, $currency, $taxStatus, $descriptionOverride));
        }

        return $collection;
    }

    /**
     * @param array<array{id: string, quantity: int, amount: float, resetStock: int}> $requestItems
     */
    private function buildFromRequestItems(array $requestItems, OrderLineItemCollection $orderLineItems, OrderDeliveryCollection $orderDeliveries, string $taxStatus, CurrencyEntity $currency, ?LineItemCollection $mollieLines, ?string $shippingDiscountLabel = null): LineItemCollection
    {
        $collection = new LineItemCollection();

        foreach ($requestItems as $item) {
            $lineItemId = (string) ($item['id'] ?? '');
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $itemAmount = (float) ($item['amount'] ?? 0.0);

            $orderLineItem = $orderLineItems->get($lineItemId);

            if (! $orderLineItem instanceof OrderLineItemEntity) {
                $delivery = $orderDeliveries->get($lineItemId);

                if ($delivery === null) {
                    throw new \RuntimeException(sprintf('Line item "%s" not found in order', $lineItemId));
                }

                $refundableQuantity = $mollieLines?->findByShopwareId($lineItemId)?->getRefundableQuantity() ?? PHP_INT_MAX;
                $quantity = min(1, $refundableQuantity);

                if ($quantity === 0) {
                    continue;
                }

                if ($itemAmount <= 0.0) {
                    $itemAmount = $delivery->getShippingCosts()->getTotalPrice();
                }

                $descriptionOverride = $delivery->getShippingCosts()->getTotalPrice() < 0 ? $shippingDiscountLabel : null;
                $refundLine = LineItem::fromDelivery($delivery, $currency, $taxStatus, $descriptionOverride);
                $deliveryMoney = new Money($itemAmount, $currency->getIsoCode());
                $refundLine->setAmount($deliveryMoney);
                $refundLine->setUnitPrice($deliveryMoney);

                $collection->add($refundLine);

                continue;
            }

            $refundableQuantity = $mollieLines?->findByShopwareId($lineItemId)?->getRefundableQuantity() ?? PHP_INT_MAX;
            $quantity = min($quantity, $refundableQuantity);

            if ($quantity === 0) {
                continue;
            }

            $unitPrice = $itemAmount > 0.0 ? $itemAmount / max(1, (int) ($item['quantity'] ?? 1)) : $orderLineItem->getUnitPrice();
            $itemAmount = $unitPrice * $quantity;

            $refundLine = LineItem::fromOrderLine($orderLineItem, $currency, $taxStatus);
            $refundLine->setQuantity($quantity);
            $unitPriceMoney = new Money($unitPrice, $currency->getIsoCode());
            $refundLine->setUnitPrice($unitPriceMoney);
            $lineAmount = new Money($itemAmount, $currency->getIsoCode());
            $refundLine->setAmount($lineAmount);

            $collection->add($refundLine);
        }

        return $collection;
    }
}
