<?php

namespace Kiener\MolliePayments\Components\RefundManager\Builder;

use Kiener\MolliePayments\Components\RefundManager\RefundData\OrderItem\DeliveryItem;
use Kiener\MolliePayments\Components\RefundManager\RefundData\OrderItem\ProductItem;
use Kiener\MolliePayments\Components\RefundManager\RefundData\OrderItem\PromotionItem;
use Kiener\MolliePayments\Components\RefundManager\RefundData\RefundData;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\OrderServiceInterface;
use Kiener\MolliePayments\Service\Refund\Item\RefundItem;
use Kiener\MolliePayments\Service\Refund\Item\RefundItemType;
use Kiener\MolliePayments\Service\Refund\Mollie\RefundMetadata;
use Kiener\MolliePayments\Service\Refund\RefundService;
use Kiener\MolliePayments\Service\Refund\RefundServiceInterface;
use Kiener\MolliePayments\Struct\MollieApi\OrderLineMetaDataStruct;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class RefundDataBuilder
{
    /**
     * @var OrderServiceInterface
     */
    private $orderService;

    /**
     * @var RefundServiceInterface
     */
    private $refundService;

    /**
     * @var Order
     */
    private $mollie;


    /**
     * @param OrderServiceInterface $orderService
     * @param RefundService $refundService
     * @param Order $mollieOrder
     */
    public function __construct(OrderServiceInterface $orderService, RefundServiceInterface $refundService, Order $mollieOrder)
    {
        $this->orderService = $orderService;
        $this->mollie = $mollieOrder;
        $this->refundService = $refundService;
    }

    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return RefundData
     */
    public function buildRefundData(OrderEntity $order, Context $context): RefundData
    {
        $orderAttributes = new OrderAttributes($order);

        /** @var null|\Mollie\Api\Resources\Order $mollieOrder */
        $mollieOrder = null;

        if ($orderAttributes->isTypeSubscription()) {
            # pure subscription orders do not
            # have a real mollie order
        } else {
            # first thing is, we have to fetch the matching Mollie order for this Shopware order
            $mollieOrderId = $this->orderService->getMollieOrderId($order);
            $mollieOrder = $this->mollie->getMollieOrder($mollieOrderId, $order->getSalesChannelId());
        }


        try {
            $refunds = $this->refundService->getRefunds($order);
        } catch (PaymentNotFoundException $ex) {
            # if we dont have a payment, then theres also no refunds
            # we still need our data, only with an empty list of refunds
            $refunds = [];
        }


        $promotionCompositions = $this->getAllPromotionCompositions($order);


        $refundItems = [];
        $refundPromotionItems = [];
        $refundDeliveryItems = [];

        if ($order->getLineItems() !== null) {
            foreach ($order->getLineItems() as $item) {
                $lineItemAttribute = new OrderLineItemEntityAttributes($item);
                $mollieOrderLineId = $lineItemAttribute->getMollieOrderLineID();

                # extract how many of this item have already been refunded
                # form all kinds of sources we have within Mollie.
                # subscriptions have no order, so no quantity is available
                $alreadyRefundedQty = 0;

                if (!$orderAttributes->isTypeSubscription() && $mollieOrder instanceof \Mollie\Api\Resources\Order) {
                    $alreadyRefundedQty = $this->getRefundedQuantity($mollieOrderLineId, $mollieOrder, $refunds);
                }

                # this is just a way to move the promotions to the last positions of our array.
                # also, shipping-free promotions have their discount item in the deliveries,...so here would just
                # be a 0,00 value line item, that we want to skip.
                if ($lineItemAttribute->isPromotion()) {
                    if ($item->getTotalPrice() !== 0.0) {
                        $refundPromotionItems[] = PromotionItem::fromOrderLineItem($item, $alreadyRefundedQty);
                    }
                } else {
                    $refundItems[] = new ProductItem($item, $promotionCompositions, $alreadyRefundedQty);
                }
            }
        }


        # now also add our delivery lines
        # these are unfortunately no line items.
        # they are saved in a separate delivery collection
        if ($order->getDeliveries() !== null) {
            /** @var OrderDeliveryEntity $delivery */
            foreach ($order->getDeliveries() as $delivery) {
                $alreadyRefundedQty = 0;

                # remember, subscriptions have no order
                if (!$orderAttributes->isTypeSubscription() && $mollieOrder instanceof \Mollie\Api\Resources\Order) {
                    # search the mollie line id for the order
                    # because we don't have this in our order items.
                    $mollieLineID = '';

                    /** @var OrderLine $line */
                    foreach ($mollieOrder->lines as $line) {
                        if ($line->metadata->orderLineItemId === $delivery->getId()) {
                            $mollieLineID = $line->id;
                            break;
                        }
                    }

                    $alreadyRefundedQty = $this->getRefundedQuantity($mollieLineID, $mollieOrder, $refunds);
                }

                if ($delivery->getShippingCosts()->getTotalPrice() < 0) {
                    $refundPromotionItems[] = PromotionItem::fromOrderDeliveryItem($delivery, $alreadyRefundedQty);
                } else {
                    $refundDeliveryItems[] = new DeliveryItem($delivery, $alreadyRefundedQty);
                }
            }
        }


        $roundingDiffTotal = 0;

        // now search all line items in Mollie that are not recognized in Shopware yet
        if ($mollieOrder instanceof \Mollie\Api\Resources\Order) {
            $lines = $mollieOrder->lines();
            /** @var OrderLine $mollieLine */
            foreach ($lines as $mollieLine) {
                $metaDataStruct = new OrderLineMetaDataStruct($mollieLine);
                if ($metaDataStruct->isRoundingItem()) {
                    $roundingDiffTotal = $metaDataStruct->getAmount();
                }
            }
        }

        # now merge all line items
        # we first need products, then promotions and as last type we add the deliveries
        $refundItems = array_merge($refundItems, $refundPromotionItems, $refundDeliveryItems);


        # now fetch some basic values from the API
        # TODO: these API calls should be removed one day, once I have more time (this refund manager is indeed huge) for now it's fine
        # ----------------------------------------------------------------------------
        try {
            $remaining = $this->refundService->getRemainingAmount($order);
            $refundedTotal = $this->refundService->getRefundedAmount($order);
            $voucherAmount = $this->refundService->getVoucherPaidAmount($order);
            # ----------------------------------------------------------------------------
            $pendingRefundAmount = $this->getPendingRefundAmount($refunds);
        } catch (PaymentNotFoundException $ex) {
            # if we don't have a payment,
            # then there are no values
            $remaining = 0;
            $refundedTotal = 0;
            $voucherAmount = 0;
            $pendingRefundAmount = 0;
        }


        return new RefundData(
            $refundItems,
            $refunds,
            $voucherAmount,
            $pendingRefundAmount,
            $refundedTotal,
            $remaining,
            $roundingDiffTotal
        );
    }


    /**
     * @param array<mixed> $refunds
     * @return float
     */
    private function getPendingRefundAmount(array $refunds): float
    {
        $pendingRefundAmount = 0;

        /** @var array<mixed> $refund */
        foreach ($refunds as $refund) {
            if ($refund['status'] === 'pending') {
                $pendingRefundAmount += (float)$refund['amount']['value'];
            }
        }

        return $pendingRefundAmount;
    }

    /**
     * @param OrderEntity $order
     * @return array<mixed>
     */
    private function getAllPromotionCompositions(OrderEntity $order): array
    {
        if ($order->getLineItems() === null) {
            return [];
        }

        $promotionCompositions = [];

        foreach ($order->getLineItems() as $item) {
            if (isset($item->getPayload()['composition'])) {
                $promotionCompositions[] = $item->getPayload()['composition'];
            }
        }

        return $promotionCompositions;
    }

    /**
     * @param string $mollieLineItemId
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @param Refund[] $refunds
     * @return int
     */
    private function getRefundedQuantity(string $mollieLineItemId, \Mollie\Api\Resources\Order $mollieOrder, array $refunds): int
    {
        $refundedQty = 0;

        # -----------------------------------------------------------------------------------------
        # try to find the refund in the Mollie Order itself (in the line items).
        # if someone refunds items directly in the Mollie Dashboard,
        # then this information might usually be in here.
        /** @var OrderLine $mollieLine */
        foreach ($mollieOrder->lines as $mollieLine) {
            if ($mollieLine->id === $mollieLineItemId) {
                $refundedQty += $mollieLine->quantityRefunded;
                break;
            }
        }

        # -----------------------------------------------------------------------------------------
        # all other refunds (partial refunds with quantities) do only work from Shopware.
        # so this is only stored because of our plugin in the metadata of the refunds.
        # we search for our item in the metadata composition of all refunds

        /** @var array<mixed> $refund */
        foreach ($refunds as $refund) {
            if (!isset($refund['metadata'])) {
                continue;
            }

            $metadata = RefundMetadata::fromArray($refund['metadata']);

            # if we do have a FULL item refund then
            # we must NOT substract our item again.
            # a full refund on the item means, that we do not have a custom amount
            # and that means that Mollie will already decrease the quantity in their data
            # which means that this is already included in our step above where we
            # count the number of refunded quantities from the order directly
            if ($metadata->getType() === RefundItemType::FULL) {
                continue;
            }

            /** @var RefundItem $item */
            foreach ($metadata->getComposition() as $item) {
                # now search for our current item
                if ($item->getMollieLineID() === $mollieLineItemId) {
                    $refundedQty += $item->getQuantity();
                    break;
                }
            }
        }

        return $refundedQty;
    }
}
