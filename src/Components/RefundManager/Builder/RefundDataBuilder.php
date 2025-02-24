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
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
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

            # **********************************************************************************
            # !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            #
            # ATTENTION, this will load the refunds from Mollie, but also from the database
            # we will add our database data to the Mollie metadata.composition and therefore "fake" a response of Mollie,
            # so that we can reuse the old code from below, even though Mollie does not really have a metadata.composition.
            $refunds = $this->refundService->getRefunds($order, $context);
        } catch (PaymentNotFoundException $ex) {
            # if we dont have a payment, then theres also no refunds
            # we still need our data, only with an empty list of refunds
            $refunds = [];
        }


        $promotionCompositions = $this->getAllPromotionCompositions($order);


        $refundItems = [];
        $refundPromotionItems = [];
        $refundDeliveryItems = [];
        $orderLineItems = $order->getLineItems();

        if ($orderLineItems !== null) {
            $orderLineItems = $orderLineItems->filter(function (OrderLineItemEntity $orderLineItemEntity) {
                return $orderLineItemEntity->getType() !== LineItem::CREDIT_LINE_ITEM_TYPE;
            });

            foreach ($orderLineItems as $item) {
                $lineItemAttribute = new OrderLineItemEntityAttributes($item);
                $mollieOrderLineId = $lineItemAttribute->getMollieOrderLineID();

                # extract how many of this item have already been refunded
                # form all kinds of sources we have within Mollie.
                # subscriptions have no order, so no quantity is available
                $alreadyRefundedQty = 0;

                if (!$orderAttributes->isTypeSubscription() && $mollieOrder instanceof \Mollie\Api\Resources\Order) {
                    $alreadyRefundedQty = $this->getRefundedQuantity($mollieOrderLineId, $mollieOrder, $refunds);
                }

                $taxTotal = round($this->calculateLineItemTaxTotal($item), 2);
                $taxPerItem = floor($taxTotal / $item->getQuantity() * 100) / 100;
                $taxDiff = round($taxTotal - ($taxPerItem * $item->getQuantity()), 2);

                # this is just a way to move the promotions to the last positions of our array.
                # also, shipping-free promotions have their discount item in the deliveries,...so here would just
                # be a 0,00 value line item, that we want to skip.
                if ($lineItemAttribute->isPromotion()) {
                    if ($item->getTotalPrice() !== 0.0) {
                        $refundPromotionItems[] = PromotionItem::fromOrderLineItem($item, $alreadyRefundedQty, $taxTotal, $taxPerItem, $taxDiff);
                    }
                } else {
                    $refundItems[] = new ProductItem($item, $promotionCompositions, $alreadyRefundedQty, $taxTotal, $taxPerItem, $taxDiff);
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

                $taxTotal = round($this->calculateDeliveryEntityTaxTotal($delivery), 2);
                $taxPerItem = floor($taxTotal / $delivery->getShippingCosts()->getQuantity() * 100) / 100;
                $taxDiff = round($taxTotal - ($taxPerItem * $delivery->getShippingCosts()->getQuantity()), 2);

                if ($delivery->getShippingCosts()->getTotalPrice() < 0) {
                    $refundPromotionItems[] = PromotionItem::fromOrderDeliveryItem($delivery, $alreadyRefundedQty, $taxTotal, $taxPerItem, $taxDiff);
                } else {
                    $refundDeliveryItems[] = new DeliveryItem($delivery, $alreadyRefundedQty, $taxTotal, $taxPerItem, $taxDiff);
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

        // get the tax status of the order
        $taxStatus = $order->getTaxStatus();

        # now fetch some basic values from the API
        # TODO: these API calls should be removed one day, once I have more time (this refund manager is indeed huge) for now it's fine
        # ----------------------------------------------------------------------------
        try {
            $remaining = $this->refundService->getRemainingAmount($order);
            $refundedTotal = $this->refundService->getRefundedAmount($order);
            $voucherAmount = $this->refundService->getVoucherPaidAmount($order);
            # ----------------------------------------------------------------------------
            $pendingRefundAmount = $this->refundService->getPendingRefundAmount($refunds);
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
            $roundingDiffTotal,
            $taxStatus
        );
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
                $promotionComposition = $item->getPayload()['composition'];

                $promotionComposition = $this->calculatePromotionCompositionTax($item, $promotionComposition);

                $promotionCompositions[] = $promotionComposition;
            }
        }

        return $promotionCompositions;
    }

    /**
     * @param OrderLineItemEntity $item
     * @param array<int, mixed> $promotionComposition
     * @return array<int, mixed>
     */
    private function calculatePromotionCompositionTax(OrderLineItemEntity $item, array $promotionComposition): array
    {
        $lineItemAttribute = new OrderLineItemEntityAttributes($item);
        if ($lineItemAttribute->isPromotion()) {
            $taxTotal = round($this->calculateLineItemTaxTotal($item), 2);
            $lineItemTotal = $item->getTotalPrice();
            $lastIndex = array_keys($promotionComposition)[count($promotionComposition) - 1];

            $taxSum = 0;

            foreach ($promotionComposition as $i => &$composition) {
                $partialTax = round($taxTotal * $composition['discount'] / $lineItemTotal, 2);

                if ($i === $lastIndex) {
                    $partialTax = -$taxTotal - $taxSum;
                }

                $composition['taxValue'] = $partialTax;
                $taxSum += $partialTax;
            }
        }

        return $promotionComposition;
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

            $meta = $refund['metadata'];

            // refund initiated within mollie dashboard, so no metadata is set
            if ($meta instanceof \stdClass) {
                continue;
            }

            if (is_string($meta)) {
                /** @var \stdClass $meta */
                $meta = json_decode($meta, true);
            }



            $metadata = RefundMetadata::fromArray($meta);

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

    /**
     * @param OrderLineItemEntity $item
     * @return float
     */
    private function calculateLineItemTaxTotal(OrderLineItemEntity $item): float
    {
        $taxTotal = 0;

        $price = $item->getPrice();

        if (!$price instanceof CalculatedPrice) {
            return $taxTotal;
        }

        return $this->calculateTax($price);
    }

    /**
     * @param OrderDeliveryEntity $delivery
     * @return float
     */
    private function calculateDeliveryEntityTaxTotal(OrderDeliveryEntity $delivery): float
    {
        $shippingCosts = $delivery->getShippingCosts();

        return $this->calculateTax($shippingCosts);
    }

    /**
     * @param CalculatedPrice $price
     * @return float
     */
    private function calculateTax(CalculatedPrice $price): float
    {
        $calculatedTaxes = $price->getCalculatedTaxes();
        $taxTotal = 0;

        foreach ($calculatedTaxes as $calculatedTax) {
            $taxTotal += $calculatedTax->getTax();
        }

        return $taxTotal;
    }
}
