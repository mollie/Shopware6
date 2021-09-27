<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\Shipment;
use Kiener\MolliePayments\Service\MolliePaymentExtractor;
use Kiener\MolliePayments\Service\OrderDeliveryService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Transition\DeliveryTransitionServiceInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bridge\Monolog\Logger;

class MollieShipment
{
    /**
     * @var MolliePaymentExtractor
     */
    private $extractor;

    /**
     * @var DeliveryTransitionServiceInterface
     */
    private $deliveryTransitionService;

    /**
     * @var Order
     */
    private $mollieApiOrderService;

    /**
     * @var Shipment
     */
    private $mollieApiShipmentService;

    /**
     * @var OrderDeliveryService
     */
    private $orderDeliveryService;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var LoggerService
     */
    private $logger;

    public function __construct(
        MolliePaymentExtractor $extractor,
        DeliveryTransitionServiceInterface $deliveryTransitionService,
        Order $mollieApiOrderService,
        Shipment $mollieApiShipmentService,
        OrderDeliveryService $orderDeliveryService,
        OrderService $orderService,
        LoggerService $logger
    )
    {
        $this->extractor = $extractor;
        $this->deliveryTransitionService = $deliveryTransitionService;
        $this->mollieApiOrderService = $mollieApiOrderService;
        $this->mollieApiShipmentService = $mollieApiShipmentService;
        $this->orderDeliveryService = $orderDeliveryService;
        $this->orderService = $orderService;
        $this->logger = $logger;
    }

    public function setShipment(string $orderDeliveryId, Context $context): bool
    {
        $delivery = $this->orderDeliveryService->getDelivery($orderDeliveryId, $context);

        if (!$delivery instanceof OrderDeliveryEntity) {
            $this->logger->addEntry(
                sprintf('Order delivery with id %s could not be found in database', $orderDeliveryId),
                $context,
                null,
                null,
                Logger::WARNING
            );

            return false;
        }

        $order = $delivery->getOrder();

        if (!$order instanceof OrderEntity) {
            $this->logger->addEntry(
                sprintf('Loaded delivery with id %s does not have an order in database', $orderDeliveryId),
                $context,
                null,
                null,
                Logger::WARNING
            );

            return false;
        }

        $customFields = $order->getCustomFields();
        $mollieOrderId = $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY] ?? null;

        if (!$mollieOrderId) {
            $this->logger->addEntry(
                sprintf('Mollie orderId does not exist in shopware order (%s)', (string)$order->getOrderNumber()),
                $context,
                null,
                null,
                Logger::WARNING
            );

            return false;
        }

        // get last transaction if it is a mollie transaction
        $lastTransaction = $this->extractor->extractLast($order->getTransactions());

        if (!$lastTransaction instanceof OrderTransactionEntity) {
            $this->logger->addEntry(
                sprintf(
                    'The last transaction of the order (%s) is not a mollie payment! No shipment will be sent to mollie',
                    (string)$order->getOrderNumber()
                ),
                $context,
                null,
                null,
                Logger::INFO
            );

            return false;
        }

        $addedMollieShipment = $this->mollieApiOrderService->setShipment($mollieOrderId, $order->getSalesChannelId(), $context);

        if ($addedMollieShipment) {
            $values = [CustomFieldsInterface::DELIVERY_SHIPPED => true];
            $this->orderDeliveryService->updateCustomFields($delivery, $values, $context);
        }

        return $addedMollieShipment;
    }

    public function shipOrder(string $orderNumber, Context $context)
    {
        $order = $this->orderService->getOrderByNumber($orderNumber, $context);

        $mollieOrderId = $this->orderService->getMollieOrderId($order);

        $shipment = $this->mollieApiShipmentService->shipOrder($mollieOrderId, $order->getSalesChannelId(), $context);

        $delivery = $order->getDeliveries()->first();

        $this->deliveryTransitionService->shipDelivery($delivery, $context);

        return $shipment;
    }

    public function shipItem(string $orderNumber, string $itemIdentifier, int $quantity, Context $context)
    {
        $order = $this->orderService->getOrderByNumber($orderNumber, $context);

        $mollieOrderId = $this->orderService->getMollieOrderId($order);

        $lineItems = $this->searchLineItem($order, $itemIdentifier);

        //TODO Refactor exceptions
        if ($lineItems->count() < 1) {
            throw new \Exception('Could not find lineItem');
        }

        if ($lineItems->count() > 1) {
            throw new \Exception('Too many lineItems found, please specify a more specific identifier.');
        }

        $lineItem = $lineItems->first();
        unset($lineItems);

        $mollieOrderLineId = $this->orderService->getMollieOrderLineId($lineItem);

        if ($quantity === 0) {
            $quantity = $lineItem->getQuantity();
            $shipments = $this->mollieApiShipmentService->getShipmentsForLineItem(
                $mollieOrderId,
                $mollieOrderLineId,
                $order->getSalesChannelId(),
                $context
            );

            /** @var \Mollie\Api\Resources\Shipment $shipment */
            foreach ($shipments as $shipment) {
                foreach ($shipment->lines() as $shipmentLineItem) {
                    if ($shipmentLineItem->id === $mollieOrderLineId) {
                        $quantity -= $shipmentLineItem->quantity;
                        break;
                    }
                }
            }
        }

        return $this->mollieApiShipmentService->shipItem(
            $mollieOrderId,
            $order->getSalesChannelId(),
            $mollieOrderLineId,
            $quantity,
            $context
        );
    }

    public function searchLineItem(OrderEntity $order, string $itemIdentifier): OrderLineItemCollection
    {
        return $order->getLineItems()->filter(function ($lineItem) use ($itemIdentifier) {
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
            if (array_key_exists('productNumber', $lineItem->getPayload()) &&
                $lineItem->getPayload()['productNumber'] === $itemIdentifier) {
                return true;
            }

            // Check itemIdentifier against the mollie order_line_id custom field
            $mollieOrderLineId = $lineItem->getCustomFields()[CustomFieldsInterface::MOLLIE_KEY]['order_line_id'] ?? null;
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
