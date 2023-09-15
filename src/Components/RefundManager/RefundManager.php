<?php

namespace Kiener\MolliePayments\Components\RefundManager;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactoryInterface;
use Kiener\MolliePayments\Components\RefundManager\Builder\RefundDataBuilder;
use Kiener\MolliePayments\Components\RefundManager\DAL\Repository\RefundRepositoryInterface;
use Kiener\MolliePayments\Components\RefundManager\Integrators\StockManagerInterface;
use Kiener\MolliePayments\Components\RefundManager\RefundData\RefundData;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequestItem;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequestItemRoundingDiff;
use Kiener\MolliePayments\Exception\CouldNotCreateMollieRefundException;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\OrderServiceInterface;
use Kiener\MolliePayments\Service\Refund\Item\RefundItem;
use Kiener\MolliePayments\Service\Refund\RefundServiceInterface;
use Kiener\MolliePayments\Struct\MollieApi\OrderLineMetaDataStruct;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Refund;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class RefundManager implements RefundManagerInterface
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
     * @var RefundDataBuilder
     */
    private $builderData;

    /**
     * @var StockManagerInterface
     */
    private $stockManager;

    /**
     * @var FlowBuilderDispatcherAdapterInterface
     */
    private $flowBuilderDispatcher;

    /**
     * @var FlowBuilderEventFactory
     */
    private $flowBuilderEventFactory;

    /**
     * @var RefundRepositoryInterface
     */
    protected $refundRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param RefundDataBuilder $refundDataBuilder
     * @param OrderServiceInterface $orderService
     * @param RefundServiceInterface $refundService
     * @param Order $mollieOrder
     * @param FlowBuilderFactoryInterface $flowBuilderFactory
     * @param FlowBuilderEventFactory $flowBuilderEventFactory
     * @param StockManagerInterface $stockUpdater
     * @param RefundRepositoryInterface $refundRepository
     * @param LoggerInterface $logger
     */
    public function __construct(RefundDataBuilder $refundDataBuilder, OrderServiceInterface $orderService, RefundServiceInterface $refundService, Order $mollieOrder, FlowBuilderFactoryInterface $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory, StockManagerInterface $stockUpdater, RefundRepositoryInterface $refundRepository, LoggerInterface $logger)
    {
        $this->builderData = $refundDataBuilder;
        $this->orderService = $orderService;
        $this->mollie = $mollieOrder;
        $this->refundService = $refundService;
        $this->stockManager = $stockUpdater;
        $this->refundRepository = $refundRepository;
        $this->logger = $logger;

        $this->flowBuilderEventFactory = $flowBuilderEventFactory;

        $this->flowBuilderDispatcher = $flowBuilderFactory->createDispatcher();
    }


    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return RefundData
     */
    public function getData(OrderEntity $order, Context $context): RefundData
    {
        return $this->builderData->buildRefundData($order, $context);
    }

    /**
     * @param OrderEntity $order
     * @param RefundRequest $request
     * @param Context $context
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return Refund
     */
    public function refund(OrderEntity $order, RefundRequest $request, Context $context): Refund
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

        $this->logger->info('Starting refund for order: ' . $order->getOrderNumber());

        # ------------------------------------------------------------------------
        # we start to build our items for the actual refund service.
        # so we iterate through our request items and make sure
        # to grab additional data that we need for the actual refund.
        $serviceItems = $this->convertToRefundItems($request, $order, $mollieOrder);

        # ------------------------------------------------------------------------
        # DIFFERENT TYPES OF REFUND
        # ------------------------------------------------------------------------

        /** @var null|Refund $refund */
        $refund = null;

        if ($request->isFullRefundAmountOnly()) {
            # we have a full refund, but only with amount
            # and no items. to make sure that we have clean data
            # we have to extract all items, so that they will be added to the metadata
            $requestItems = $this->buildRequestItemsFromOrder($order);
            $request->setItems($requestItems);
            $this->appendRoundingItemFromMollieOrder($request, $mollieOrder);

            # convert again
            $serviceItems = $this->convertToRefundItems($request, $order, $mollieOrder);


            if ($orderAttributes->isTypeSubscription()) {
                # we only have a transaction in the case of a subscription
                $refund = $this->refundService->refundPartial(
                    $order,
                    $request->getDescription(),
                    $request->getInternalDescription(),
                    $order->getAmountTotal(),
                    $serviceItems,
                    $context
                );
            } else {
                $refund = $this->refundService->refundFull(
                    $order,
                    $request->getDescription(),
                    $request->getInternalDescription(),
                    $serviceItems,
                    $context
                );
            }
        } elseif ($request->isFullRefundWithItems($order)) {
            $this->appendRoundingItemFromMollieOrder($request, $mollieOrder);
            $serviceItems = $this->convertToRefundItems($request, $order, $mollieOrder);
            $refund = $this->refundService->refundFull(
                $order,
                $request->getDescription(),
                $request->getInternalDescription(),
                $serviceItems,
                $context
            );
        } elseif ($request->isPartialAmountOnly()) {
            $refund = $this->refundService->refundPartial(
                $order,
                $request->getDescription(),
                $request->getInternalDescription(),
                (float)$request->getAmount(),
                [],
                $context
            );
        } elseif ($request->isPartialAmountWithItems($order)) {
            $refund = $this->refundService->refundPartial(
                $order,
                $request->getDescription(),
                $request->getInternalDescription(),
                (float)$request->getAmount(),
                $serviceItems,
                $context
            );
        }


        if (!$refund instanceof Refund) {
            # a problem happened, lets finish with an exception
            throw new CouldNotCreateMollieRefundException('', (string)$order->getOrderNumber());
        }

        $refundAmount = (float)$refund->amount->value;


        # SAVE LOCAL REFUND
        # ---------------------------------------------------------------------------------------------
        $this->refundRepository->create(
            [
                [
                    'orderId' => $order->getId(),
                    'orderVersionId' => $order->getVersionId(),
                    'mollieRefundId' => $refund->id,
                    'publicDescription' => $request->getDescription(),
                    'internalDescription' => $request->getInternalDescription(),
                ]
            ],
            $context
        );


        # DISPATCH FLOW BUILDER
        # ---------------------------------------------------------------------------------------------
        $event = $this->flowBuilderEventFactory->buildRefundStartedEvent($order, $refundAmount, $context);
        $this->flowBuilderDispatcher->dispatch($event);


        # ---------------------------------------------------------------------------------------------
        # RESET STOCK
        # if everything worked above, iterate through all our
        # refund items and increase their stock
        foreach ($request->getItems() as $item) {
            # skip if nothing should be added to the stock
            if ($item->getStockIncreaseQty() <= 0) {
                continue;
            }

            $orderItem = $this->getOrderItem($order, $item->getLineId());

            if ($orderItem instanceof OrderLineItemEntity) {
                # and now simply call our stock manager
                $this->stockManager->increaseStock(
                    $orderItem,
                    $item->getStockIncreaseQty(),
                    $refund->id
                );
            }
        }

        return $refund;
    }

    /**
     * @param string $orderId
     * @param string $refundId
     * @param Context $context
     * @return bool
     */
    public function cancelRefund(string $orderId, string $refundId, Context $context): bool
    {
        $order = $this->orderService->getOrder($orderId, $context);

        $this->logger->info(
            'Starting to cancel a refund for order: ' . $order->getOrderNumber(),
            [
                'refundId' => $refundId,
            ]
        );

        return $this->refundService->cancel($order, $refundId);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param string $lineID
     * @return OrderLineItemEntity
     */
    private function getOrderItem(OrderEntity $orderEntity, string $lineID): ?OrderLineItemEntity
    {
        if ($orderEntity->getLineItems() === null) {
            return null;
        }

        foreach ($orderEntity->getLineItems() as $itemKey => $orderItem) {
            if ($itemKey === $lineID) {
                return $orderItem;
            }
        }

        return null;
    }


    /**
     * @param OrderEntity $order
     * @return array<mixed>
     */
    private function buildRequestItemsFromOrder(OrderEntity $order)
    {
        $items = [];

        if ($order->getLineItems() instanceof OrderLineItemCollection) {
            foreach ($order->getLineItems() as $lineItem) {
                $items[] = new RefundRequestItem(
                    $lineItem->getId(),
                    $lineItem->getTotalPrice(),
                    $lineItem->getQuantity(),
                    0
                );
            }
        }

        if ($order->getDeliveries() instanceof OrderDeliveryCollection) {
            foreach ($order->getDeliveries() as $delivery) {
                $items[] = new RefundRequestItem(
                    $delivery->getId(),
                    $delivery->getShippingCosts()->getTotalPrice(),
                    $delivery->getShippingCosts()->getQuantity(),
                    0
                );
            }
        }

        return $items;
    }

    private function appendRoundingItemFromMollieOrder(RefundRequest $request, ?\Mollie\Api\Resources\Order $mollieOrder): void
    {
        if ($mollieOrder === null) {
            return;
        }
        $lines = $mollieOrder->lines();

        /** @var OrderLine $line */
        foreach ($lines as $line) {
            $lineMetadataStruct = new OrderLineMetaDataStruct($line);
            if ($lineMetadataStruct->isRoundingItem() === false) {
                continue;
            }

            $refundRequestItem = new RefundRequestItemRoundingDiff(
                $lineMetadataStruct->getId(),
                $lineMetadataStruct->getAmount(),
                $lineMetadataStruct->getQuantity(),
                0
            );
            $request->addItem($refundRequestItem);
        }
    }

    /**
     * @param RefundRequest $request
     * @param OrderEntity $order
     * @param ?\Mollie\Api\Resources\Order $mollieOrder
     * @return RefundItem[]
     */
    private function convertToRefundItems(RefundRequest $request, OrderEntity $order, ?\Mollie\Api\Resources\Order $mollieOrder): array
    {
        $serviceItems = [];

        foreach ($request->getItems() as $requestItem) {
            $mollieLineID = '';
            $shopwareReferenceID = '';

            $orderItem = $this->getOrderItem($order, $requestItem->getLineId());

            # if we have a real line item, then extract
            # the external Mollie Line ID from it
            if ($orderItem instanceof OrderLineItemEntity) {
                $orderItemAttributes = new OrderLineItemEntityAttributes($orderItem);
                $mollieLineID = $orderItemAttributes->getMollieOrderLineID();

                if (isset($orderItem->getPayload()['productNumber'])) {
                    $shopwareReferenceID = (string)$orderItem->getPayload()['productNumber'];
                } else {
                    $shopwareReferenceID = (string)$orderItem->getReferencedId();
                }
            } else {
                # yeah i know complexity...but for now lets keep it compact :)
                if ($order->getDeliveries() instanceof OrderDeliveryCollection && $mollieOrder instanceof \Mollie\Api\Resources\Order) {
                    # if we do not have an item
                    # then it might be a delivery in Shopware
                    foreach ($order->getDeliveries() as $delivery) {
                        # check if the current delivery ID is the one from our
                        # request item that we should refund. then a delivery needs to be refunded
                        if ($delivery->getId() === $requestItem->getLineId()) {
                            # now just extract the line item id from our Mollie line items
                            /** @var OrderLine $line */
                            foreach ($mollieOrder->lines as $line) {
                                if ($line->metadata->orderLineItemId === $delivery->getId()) {
                                    $mollieLineID = $line->id;
                                    $shopwareReferenceID = ($delivery->getShippingMethod() !== null) ? $delivery->getShippingMethod()->getName() : '';
                                    break;
                                }
                            }
                        }
                    }
                }
                if ($requestItem instanceof RefundRequestItemRoundingDiff) {
                    $mollieLineID = $requestItem->getLineId();
                }
            }

            # if we didn't find a valid mollie line ID
            # then it's just not existing in Mollie.
            # this can happen on free shippings...
            if (empty($mollieLineID)) {
                continue;
            }

            # finally, build our refund item
            # for our refund service with all
            # required information
            $serviceItems[] = new RefundItem(
                $requestItem->getLineId(),
                $mollieLineID,
                (string)$shopwareReferenceID,
                $requestItem->getQuantity(),
                $requestItem->getAmount()
            );
        }

        return $serviceItems;
    }
}
