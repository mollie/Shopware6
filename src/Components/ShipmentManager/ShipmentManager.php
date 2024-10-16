<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ShipmentManager;

use Kiener\MolliePayments\Components\ShipmentManager\Exceptions\NoDeliveriesFoundException;
use Kiener\MolliePayments\Components\ShipmentManager\Exceptions\NoLineItemsProvidedException;
use Kiener\MolliePayments\Components\ShipmentManager\Models\TrackingData;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\MollieStatus;
use Kiener\MolliePayments\Exception\OrderLineItemFoundManyException;
use Kiener\MolliePayments\Exception\OrderLineItemNotFoundException;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\MollieApi\Models\MollieShippingItem;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\OrderDeliveryExtractor;
use Kiener\MolliePayments\Service\MollieApi\OrderItemsExtractor;
use Kiener\MolliePayments\Service\MollieApi\ShipmentInterface;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\TrackingInfoStructFactory;
use Kiener\MolliePayments\Service\Transition\DeliveryTransitionServiceInterface;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class ShipmentManager implements ShipmentManagerInterface
{

    /**
     * @var DeliveryTransitionServiceInterface
     */
    private $deliveryTransitionService;

    /**
     * @var Order
     */
    private $mollieApiOrderService;

    /**
     * @var ShipmentInterface
     */
    private $shipmentService;


    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var OrderDeliveryExtractor
     */
    private $orderDataExtractor;

    /**
     * @var OrderItemsExtractor
     */
    private $orderItemsExtractor;

    /**
     * @var TrackingInfoStructFactory
     */
    private $trackingFactory;

    /**
     * @param DeliveryTransitionServiceInterface $deliveryTransitionService
     * @param Order $mollieApiOrderService
     * @param ShipmentInterface $shipmentService
     * @param OrderService $orderService
     * @param OrderDeliveryExtractor $orderDataExtractor
     * @param OrderItemsExtractor $orderItemsExtractor
     * @param TrackingInfoStructFactory $trackingFactory
     */
    public function __construct(DeliveryTransitionServiceInterface $deliveryTransitionService, Order $mollieApiOrderService, ShipmentInterface $shipmentService, OrderService $orderService, OrderDeliveryExtractor $orderDataExtractor, OrderItemsExtractor $orderItemsExtractor, TrackingInfoStructFactory $trackingFactory)
    {
        $this->deliveryTransitionService = $deliveryTransitionService;
        $this->mollieApiOrderService = $mollieApiOrderService;
        $this->shipmentService = $shipmentService;
        $this->orderService = $orderService;
        $this->orderDataExtractor = $orderDataExtractor;
        $this->orderItemsExtractor = $orderItemsExtractor;
        $this->trackingFactory = $trackingFactory;
    }


    /**
     * @param string $orderId
     * @param Context $context
     * @return array<mixed>
     */
    public function getStatus(string $orderId, Context $context): array
    {
        $order = $this->orderService->getOrder($orderId, $context);
        $mollieOrderId = $this->orderService->getMollieOrderId($order);

        return $this->shipmentService->getStatus($mollieOrderId, $order->getSalesChannelId());
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return array<string, numeric>
     */
    public function getTotals(string $orderId, Context $context): array
    {
        $order = $this->orderService->getOrder($orderId, $context);
        $mollieOrderId = $this->orderService->getMollieOrderId($order);

        return $this->shipmentService->getTotals($mollieOrderId, $order->getSalesChannelId());
    }

    /**
     * @param OrderEntity $order
     * @param null|TrackingData $tracking
     * @param array<mixed> $shippingItems
     * @param Context $context
     * @throws NoDeliveriesFoundException
     * @throws NoLineItemsProvidedException
     * @return \Mollie\Api\Resources\Shipment
     */
    public function shipOrder(OrderEntity $order, ?TrackingData $tracking, array $shippingItems, Context $context): \Mollie\Api\Resources\Shipment
    {
        if (empty($shippingItems)) {
            throw new NoLineItemsProvidedException('Please provide a valid list of line items that should be shipped!');
        }


        if ($tracking instanceof TrackingData) {
            $trackingData = $this->trackingFactory->create(
                $tracking->getCarrier(),
                $tracking->getCode(),
                $tracking->getTrackingUrl()
            );
        } else {
            $trackingData = $this->trackingFactory->trackingFromOrder($order);
        }


        $orderAttr = new OrderAttributes($order);

        $mollieOrderId = $orderAttr->getMollieOrderId();

        $mollieShippingItems = [];

        # we have to look up our Mollie LineItem IDs from the order line items.
        # so we iterate through both of our lists and search it
        $orderLineItems = $order->getLineItems();

        if ($orderLineItems instanceof OrderLineItemCollection) {
            foreach ($shippingItems as $shippingItem) {
                foreach ($orderLineItems as $orderLineItem) {
                    # now search the order line item by our provided shopware ID
                    if ($orderLineItem->getId() === $shippingItem->getShopwareId()) {

                        # extract the Mollie order line ID from our custom fields
                        $attr = new OrderLineItemEntityAttributes($orderLineItem);
                        $mollieID = $attr->getMollieOrderLineID();

                        $mollieShippingItems[] = new MollieShippingItem(
                            $mollieID,
                            $shippingItem->getQuantity()
                        );

                        break;
                    }
                }
            }
        }

        $shipment = $this->shipmentService->shipOrder(
            $mollieOrderId,
            $order->getSalesChannelId(),
            $mollieShippingItems,
            $trackingData
        );

        # --------------------------------------------------------------------------------------
        # post-shipping processing

        $this->transitionOrder($order, $mollieOrderId, $context);


        return $shipment;
    }

    /**
     * @param OrderEntity $order
     * @param null|TrackingData $tracking
     * @param Context $context
     * @throws \Exception
     * @return \Mollie\Api\Resources\Shipment
     */
    public function shipOrderRest(OrderEntity $order, ?TrackingData $tracking, Context $context): \Mollie\Api\Resources\Shipment
    {
        if ($tracking instanceof TrackingData) {
            $trackingData = $this->trackingFactory->create(
                $tracking->getCarrier(),
                $tracking->getCode(),
                $tracking->getTrackingUrl()
            );
        } else {
            $trackingData = $this->trackingFactory->trackingFromOrder($order);
        }

        $orderAttr = new OrderAttributes($order);

        $mollieOrderId = $orderAttr->getMollieOrderId();

        # ship order with empty array
        # so that the Mollie shipAll is being triggered
        # which always ships everything or just the rest
        $shipment = $this->shipmentService->shipOrder(
            $mollieOrderId,
            $order->getSalesChannelId(),
            [],
            $trackingData
        );

        # --------------------------------------------------------------------------------------
        # post-shipping processing

        $this->transitionOrder($order, $mollieOrderId, $context);

        return $shipment;
    }

    /**
     * @param OrderEntity $order
     * @param string $itemIdentifier
     * @param int $quantity
     * @param null|TrackingData $tracking
     * @param Context $context
     * @throws \Exception
     * @return \Mollie\Api\Resources\Shipment
     */
    public function shipItem(OrderEntity $order, string $itemIdentifier, int $quantity, ?TrackingData $tracking, Context $context): \Mollie\Api\Resources\Shipment
    {
        $mollieOrderId = $this->orderService->getMollieOrderId($order);

        $lineItems = $this->findMatchingLineItems($order, $itemIdentifier, $context);

        if ($lineItems->count() > 1) {
            throw new OrderLineItemFoundManyException($itemIdentifier);
        }

        $lineItem = $lineItems->first();
        unset($lineItems);

        if (!$lineItem instanceof OrderLineItemEntity) {
            throw new OrderLineItemNotFoundException($itemIdentifier);
        }


        if ($tracking instanceof TrackingData) {
            $mollieTracking = $this->trackingFactory->create(
                $tracking->getCarrier(),
                $tracking->getCode(),
                $tracking->getTrackingUrl()
            );
        } else {
            $mollieTracking = $this->trackingFactory->trackingFromOrder($order);
        }

        $mollieOrderLineId = $this->orderService->getMollieOrderLineId($lineItem);

        # if we did not provide a quantity
        # we ship everything that is left and shippable
        if ($quantity === 0) {
            $quantity = $this->mollieApiOrderService->getMollieOrderLine(
                $mollieOrderId,
                $mollieOrderLineId,
                $order->getSalesChannelId()
            )->shippableQuantity;
        }

        $shipment = $this->shipmentService->shipItem(
            $mollieOrderId,
            $order->getSalesChannelId(),
            $mollieOrderLineId,
            $quantity,
            $mollieTracking
        );

        # --------------------------------------------------------------------------------------
        # post-shipping processing

        $this->transitionOrder($order, $mollieOrderId, $context);


        return $shipment;
    }

    /**
     * @param OrderEntity $order
     * @param string $mollieOrderId
     * @param Context $context
     * @return void
     */
    private function transitionOrder(OrderEntity $order, string $mollieOrderId, Context $context): void
    {
        $delivery = $this->orderDataExtractor->extractDelivery($order, $context);

        # we need to see if our order is now "complete"
        # if its complete it can be marked as fully shipped
        # if not, then its only partially shipped
        $mollieOrder = $this->mollieApiOrderService->getMollieOrder($mollieOrderId, $order->getSalesChannelId());

        if ($mollieOrder->status === MollieStatus::COMPLETED) {
            $this->deliveryTransitionService->shipDelivery($delivery, $context);
        } else {
            $this->deliveryTransitionService->partialShipDelivery($delivery, $context);
        }
    }

    /**
     * Try to find lineItems matching the $itemIdentifier. Shopware does not have a unique human-readable identifier for
     * order line items, so we have to check for several fields, like product number or the mollie order line id.
     *
     * @param OrderEntity $order
     * @param string $itemIdentifier
     * @param Context $context
     * @return OrderLineItemCollection
     */
    private function findMatchingLineItems(OrderEntity $order, string $itemIdentifier, Context $context): OrderLineItemCollection
    {
        return $this->orderItemsExtractor->extractLineItems($order)->filter(function ($lineItem) use ($itemIdentifier) {
            /** @var OrderLineItemEntity $lineItem */

            // Default Shopware: If the lineItem is of type "product" and has an associated ProductEntity,
            // check if the itemIdentifier matches the product's product number.
            if ($lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE &&
                $lineItem->getProduct() instanceof ProductEntity &&
                $lineItem->getProduct()->getProductNumber() === $itemIdentifier) {
                return true;
            }

            // If it's not a "product" type lineItem, for example if it's a completely custom lineItem type,
            // check if the payload has a productNumber in it that matches the itemIdentifier.
            if (!empty($lineItem->getPayload()) &&
                array_key_exists('productNumber', $lineItem->getPayload()) &&
                $lineItem->getPayload()['productNumber'] === $itemIdentifier) {
                return true;
            }

            // Check itemIdentifier against the mollie order_line_id custom field
            $customFields = $lineItem->getCustomFields() ?? [];
            $mollieOrderLineId = $customFields[CustomFieldsInterface::MOLLIE_KEY]['order_line_id'] ?? null;
            if (!is_null($mollieOrderLineId) && $mollieOrderLineId === $itemIdentifier) {
                return true;
            }

            // If it hasn't passed any of the above tests, check if the itemIdentifier is a valid Uuid...
            if (!Uuid::isValid($itemIdentifier)) {
                return false;
            }

            // ... and then check if it matches the Id of the entity the lineItem is referencing,
            // or if it matches the Id of the lineItem itself.
            if ($lineItem->getReferencedId() === $itemIdentifier || $lineItem->getId() === $itemIdentifier) {
                return true;
            }

            // Otherwise, this lineItem does not match the itemIdentifier at all.
            return false;
        });
    }
}
