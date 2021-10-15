<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Exception\OrderLineItemFoundManyException;
use Kiener\MolliePayments\Exception\OrderLineItemNotFoundException;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
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
     * @var OrderDataExtractor
     */
    private $orderDataExtractor;

    /**
     * @var LoggerService
     */
    private $logger;

    /**
     * @param MolliePaymentExtractor $extractor
     * @param DeliveryTransitionServiceInterface $deliveryTransitionService
     * @param Order $mollieApiOrderService
     * @param Shipment $mollieApiShipmentService
     * @param OrderDeliveryService $orderDeliveryService
     * @param OrderService $orderService
     * @param OrderDataExtractor $orderDataExtractor
     * @param LoggerService $logger
     */
    public function __construct(
        MolliePaymentExtractor $extractor,
        DeliveryTransitionServiceInterface $deliveryTransitionService,
        Order $mollieApiOrderService,
        Shipment $mollieApiShipmentService,
        OrderDeliveryService $orderDeliveryService,
        OrderService $orderService,
        OrderDataExtractor $orderDataExtractor,
        LoggerService $logger
    )
    {
        $this->extractor = $extractor;
        $this->deliveryTransitionService = $deliveryTransitionService;
        $this->mollieApiOrderService = $mollieApiOrderService;
        $this->mollieApiShipmentService = $mollieApiShipmentService;
        $this->orderDeliveryService = $orderDeliveryService;
        $this->orderService = $orderService;
        $this->orderDataExtractor = $orderDataExtractor;
        $this->logger = $logger;
    }

    /**
     * @param string $orderDeliveryId
     * @param Context $context
     * @return bool
     */
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

    /**
     * @param string $orderNumber
     * @param Context $context
     * @return \Mollie\Api\Resources\Shipment
     */
    public function shipOrder(string $orderNumber, Context $context): \Mollie\Api\Resources\Shipment
    {
        $order = $this->orderService->getOrderByNumber($orderNumber, $context);

        $mollieOrderId = $this->orderService->getMollieOrderId($order);

        $shipment = $this->mollieApiShipmentService->shipOrder($mollieOrderId, $order->getSalesChannelId(), $context);

        $delivery = $this->orderDataExtractor->extractDelivery($order, $context);

        $this->deliveryTransitionService->shipDelivery($delivery, $context);

        return $shipment;
    }

    /**
     * @param string $orderNumber
     * @param string $itemIdentifier
     * @param int $quantity
     * @param Context $context
     * @return \Mollie\Api\Resources\Shipment
     */
    public function shipItem(string $orderNumber, string $itemIdentifier, int $quantity, Context $context): \Mollie\Api\Resources\Shipment
    {
        $order = $this->orderService->getOrderByNumber($orderNumber, $context);

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

        $mollieOrderLineId = $this->orderService->getMollieOrderLineId($lineItem);

        if ($quantity === 0) {
            $quantity = $this->mollieApiOrderService->getMollieOrderLine(
                $mollieOrderId,
                $mollieOrderLineId,
                $order->getSalesChannelId(),
                $context
            )->shippableQuantity;
        }

        $shipment = $this->mollieApiShipmentService->shipItem(
            $mollieOrderId,
            $order->getSalesChannelId(),
            $mollieOrderLineId,
            $quantity,
            $context
        );

        $delivery = $this->orderDataExtractor->extractDelivery($order, $context);

        if ($this->mollieApiOrderService->isCompletelyShipped($mollieOrderId, $order->getSalesChannelId(), $context)) {
            $this->deliveryTransitionService->shipDelivery($delivery, $context);
        } else {
            $this->deliveryTransitionService->partialShipDelivery($delivery, $context);
        }

        return $shipment;
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
        return $this->orderDataExtractor->extractLineItems($order, $context)->filter(function ($lineItem) use ($itemIdentifier) {
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
