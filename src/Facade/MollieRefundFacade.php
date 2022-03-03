<?php

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Refund\Item\MollieRefundItem;
use Kiener\MolliePayments\Service\Refund\RefundService;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Mollie\Api\Resources\Refund;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class MollieRefundFacade
{

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var RefundService
     */
    private $refundService;

    /**
     * @var Order
     */
    private $mollie;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param OrderService $orderService
     * @param RefundService $refundService
     * @param Order $mollieOrder
     * @param LoggerInterface $logger
     */
    public function __construct(OrderService $orderService, RefundService $refundService, Order $mollieOrder, LoggerInterface $logger)
    {
        $this->orderService = $orderService;
        $this->mollie = $mollieOrder;
        $this->refundService = $refundService;
        $this->logger = $logger;
    }


    /**
     * @param OrderEntity $order
     * @param string $description
     * @param float|null $amount
     * @param array $requestItems
     * @param Context $context
     * @return Refund
     * @throws \Doctrine\DBAL\Exception
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function startRefundProcess(OrderEntity $order, string $description, ?float $amount, array $requestItems, Context $context): Refund
    {
        $this->logger->info(sprintf('Refund for order %s with amount %s is triggered through the Shopware administration.', $order->getOrderNumber(), $amount));


        if (strlen(trim($description)) === 0) {
            $description = "Refunded through Shopware administration. Order number: " . $order->getOrderNumber();
        }

        $mollieOrderId = $this->orderService->getMollieOrderId($order);
        $mollieOrder = $this->mollie->getMollieOrder($mollieOrderId, '');

        $refundItems = [];
        foreach ($requestItems as $item) {
            $lineID = (string)$item['id'];
            $orderItem = $this->getOrderItem($order, $lineID);

            $mollieLineID = '';

            if ($orderItem !== null) {
                $orderItemAttributes = new OrderLineItemEntityAttributes($orderItem);

                $mollieLineID = $orderItemAttributes->getMollieOrderLineID();
            }

            if ($orderItem === null) {
                # shipping? # TODO
                foreach ($order->getDeliveries() as $delivery) {
                    if ($delivery->getId() === $lineID) {

                        foreach ($mollieOrder->lines as $line) {
                            if ($line->metadata->orderLineItemId === $delivery->getId()) {
                                $mollieLineID = $line->id;
                                break;
                            }
                        }

                    }
                }
            }


            $refundItems[] = new MollieRefundItem(
                (string)$item['id'],
                $mollieLineID,
                (string)$item['quantity'],
                (float)$item['amount'],
                (int)$item['resetStock'],
                $orderItem
            );
        }


        if ($amount === null) {
            return $this->refundService->refundFull(
                $order,
                $description,
                $context
            );
        }


        $isDifferentAmount = false;
        /** @var MollieRefundItem $item */
        foreach ($refundItems as $item) {

            if ($item->getQuantity() <= 0 && $item->getAmount() <= 0) {
                continue;
            }

            foreach ($order->getLineItems() as $orderItem) {

                if ($orderItem->getId() === $item->getShopwareLineID()) {
                    if ($orderItem->getUnitPrice() !== $item->getAmount()) {
                        $isDifferentAmount = true;
                        break;
                    }
                }
            }
        }

        if ($isDifferentAmount) {
            return $this->refundService->refundAmount(
                $order,
                $description,
                $amount,
                $refundItems,
                $context
            );
        }

        return $this->refundService->refundItems(
            $order,
            $description,
            $refundItems,
            $context
        );
    }


    /**
     * @param string $orderId
     * @param string $refundId
     * @param Context $context
     * @return bool
     */
    public function cancelUsingOrderId(string $orderId, string $refundId, Context $context): bool
    {
        $order = $this->orderService->getOrder($orderId, $context);

        $this->logger->info(sprintf('Refund with id %s for order %s was cancelled through the Shopware administration.', $refundId, $order->getOrderNumber()));

        return $this->refundService->cancel($order, $refundId);
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return array<mixed>
     */
    public function getMollieRefundData(string $orderId, Context $context): array
    {
        $order = $this->orderService->getOrder($orderId, $context);

        $mollieOrderId = $this->orderService->getMollieOrderId($order);

        $mollieOrder = $this->mollie->getMollieOrder($mollieOrderId, '');


        $remaining = $this->refundService->getRemainingAmount($order);
        $refundedTotal = $this->refundService->getRefundedAmount($order);
        $voucherAmount = $this->refundService->getVoucherPaidAmount($order);
        $refunds = $this->refundService->getRefunds($order);


        $pendingRefundAmount = 0;

        /** @var Refund $refund */
        foreach ($refunds as $refund) {
            if ($refund['status'] === 'pending') {
                $pendingRefundAmount += (float)$refund['amount']['value'];
            }
        }

        $promotionCompositions = [];
        foreach ($order->getLineItems() as $item) {
            if (isset($item->getPayload()['composition'])) {
                $promotionCompositions[] = $item->getPayload()['composition'];
            }
        }

        $cartItems = [];
        $cartPromotions = [];

        foreach ($order->getLineItems() as $item) {

            $productNumber = '';

            if (isset($item->getPayload()['productNumber'])) {
                $productNumber = $item->getPayload()['productNumber'];
            }

            $lineItemAttribute = new OrderLineItemEntityAttributes($item);

            $refunded = 0;
            foreach ($mollieOrder->lines as $mollieLine) {
                if ($mollieLine->id === $lineItemAttribute->getMollieOrderLineID()) {
                    $refunded += $mollieLine->quantityRefunded;
                    break;
                }
            }


            # also search in meta data
            /** @var Refund $refund */
            foreach ($refunds as $refund) {
                if (isset($refund['metadata']) && isset($refund['metadata']['composition'])) {
                    foreach ($refund['metadata']['composition'] as $composition) {
                        if ($composition['lineItemId'] === $lineItemAttribute->getMollieOrderLineID()) {
                            $refunded += (int)$composition['quantity'];
                            break;
                        }
                    }
                }
            }

            $promotionDiscount = 0;
            $promotionQuantity = 0;
            foreach ($promotionCompositions as $composition) {
                foreach ($composition as $compItem) {

                    if ($compItem['id'] === $item->getReferencedId()) {
                        $promotionDiscount += round((float)$compItem['discount'], 2);
                        $promotionQuantity += (int)$compItem['quantity'];
                    }
                }
            }

            $isPromotion = false;
            if (isset($item->getPayload()['composition'])) {
                $isPromotion = true;

                $productNumber = $item->getReferencedId();
            }

            $item = [
                'shopware' => [
                    'id' => $item->getId(),
                    'label' => $item->getLabel(),
                    'unitPrice' => round($item->getUnitPrice(), 2),
                    'quantity' => $item->getQuantity(),
                    'totalPrice' => round($item->getTotalPrice(), 2),
                    'discountedPrice' => round($item->getTotalPrice() - $promotionDiscount, 2),
                    'productNumber' => $productNumber,
                    'promotion' => [
                        'discount' => $promotionDiscount,
                        'quantity' => $promotionQuantity,
                    ],
                    'isPromotion' => $isPromotion,
                    'isDelivery' => false,
                ],
                // refund mode: none, quantity, amount
                'refundMode' => 'none',
                'refundQuantity' => 0,
                'refundAmount' => 0,
                'resetStock' => 0,
                'refundPromotion' => false,
                'refunded' => $refunded,
            ];

            if (!$isPromotion) {
                $cartItems[] = $item;
            } else {
                $cartPromotions[] = $item;
            }

        }

        $cartItems = array_merge($cartItems, $cartPromotions);

        foreach ($order->getDeliveries() as $delivery) {

            # shipping? # TODO

            $refunded = 0;

            foreach ($mollieOrder->lines as $line) {
                if ($line->metadata->orderLineItemId === $delivery->getId()) {
                    $refunded += $line->quantityRefunded;
                    break;
                }
            }

            $item = [
                'shopware' => [
                    'id' => $delivery->getId(),
                    'label' => $delivery->getShippingMethod()->getName(),
                    'unitPrice' => round($delivery->getShippingCosts()->getUnitPrice(), 2),
                    'quantity' => $delivery->getShippingCosts()->getQuantity(),
                    'totalPrice' => round($delivery->getShippingCosts()->getTotalPrice(), 2),
                    'productNumber' => '',
                    'promotion' => [
                        'discount' => 0,
                        'quantity' => 0,
                    ],
                    'isPromotion' => false,
                    'isDelivery' => true,
                ],
                // refund mode: none, quantity, amount
                'refundMode' => 'none',
                'refundQuantity' => 0,
                'refundAmount' => 0,
                'resetStock' => 0,
                'refunded' => $refunded,
            ];

            $cartItems[] = $item;
        }
        # TODO float array problem
        return [
            'totals' => [
                'remaining' => round($remaining, 2),
                'voucherAmount' => round($voucherAmount, 2),
                'pendingRefunds' => round($pendingRefundAmount, 2),
                'refunded' => round($refundedTotal, 2),
            ],
            'cart' => $cartItems,
            'refunds' => $refunds,
        ];
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return array<mixed>
     */
    public function getTotalsByOrderId(string $orderId, Context $context): array
    {
        $order = $this->orderService->getOrder($orderId, $context);

        $remaining = $this->refundService->getRemainingAmount($order);
        $refunded = $this->refundService->getRefundedAmount($order);
        $voucherAmount = $this->refundService->getVoucherPaidAmount($order);
        $refunds = $this->refundService->getRefunds($order);

        $pendingRefundAmount = 0;


        /** @var Refund $refund */
        foreach ($refunds as $refund) {
            if ($refund['status'] === 'pending') {
                $pendingRefundAmount += (float)$refund['amount']['value'];
            }
        }

        return [
            'remaining' => round($remaining, 2),
            'refunded' => round($refunded, 2),
            'voucherAmount' => round($voucherAmount, 2),
            'pendingRefunds' => round($pendingRefundAmount, 2),
        ];
    }

    /**
     * @param OrderEntity $orderEntity
     * @param string $lineID
     * @return OrderLineItemEntity
     */
    private function getOrderItem(OrderEntity $orderEntity, string $lineID): ?OrderLineItemEntity
    {
        foreach ($orderEntity->getLineItems() as $itemKey => $orderItem) {
            if ($itemKey === $lineID) {
                return $orderItem;
            }
        }

        return null;
    }

}
