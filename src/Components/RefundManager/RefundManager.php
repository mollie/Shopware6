<?php

namespace Kiener\MolliePayments\Components\RefundManager;


use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactoryInterface;
use Kiener\MolliePayments\Components\RefundManager\Builder\RefundDataBuilder;
use Kiener\MolliePayments\Components\RefundManager\Integrators\StockManagerInterface;
use Kiener\MolliePayments\Components\RefundManager\RefundData\RefundData;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Exception\CouldNotCreateMollieRefundException;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\OrderServiceInterface;
use Kiener\MolliePayments\Service\Refund\Item\RefundItem;
use Kiener\MolliePayments\Service\Refund\RefundService;
use Kiener\MolliePayments\Service\Refund\RefundServiceInterface;
use Kiener\MolliePayments\Service\Stock\StockManager;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Refund;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class RefundManager
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
     * @param LoggerInterface $logger
     * @throws \Exception
     */
    public function __construct(RefundDataBuilder $refundDataBuilder, OrderServiceInterface $orderService, RefundServiceInterface $refundService, Order $mollieOrder, FlowBuilderFactoryInterface $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory, StockManagerInterface $stockUpdater, LoggerInterface $logger)
    {
        $this->builderData = $refundDataBuilder;
        $this->orderService = $orderService;
        $this->mollie = $mollieOrder;
        $this->refundService = $refundService;
        $this->stockManager = $stockUpdater;
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
     * @return Refund
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function refund(OrderEntity $order, RefundRequest $request, Context $context): Refund
    {
        $mollieOrderId = $this->orderService->getMollieOrderId($order);
        $mollieOrder = $this->mollie->getMollieOrder($mollieOrderId, $order->getSalesChannelId());

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
            # full amount refunds cannot be done without items
            # so we have to do a partial refund with the full amount
            $refund = $this->refundService->refundPartial(
                $order,
                $request->getDescription(),
                $order->getAmountTotal(),
                [],
                $context
            );
        } else if ($request->isFullRefundWithItems($order)) {
            $refund = $this->refundService->refundFull(
                $order,
                $request->getDescription(),
                $serviceItems,
                $context
            );
        } else if ($request->isPartialAmountOnly()) {
            $refund = $this->refundService->refundPartial(
                $order,
                $request->getDescription(),
                (float)$request->getAmount(),
                [],
                $context
            );
        } elseif ($request->isPartialAmountWithItems($order)) {
            $refund = $this->refundService->refundPartial(
                $order,
                $request->getDescription(),
                (float)$request->getAmount(),
                $serviceItems,
                $context
            );
        }


        if (!$refund instanceof Refund) {
            # a problem happened, lets finish with an exception
            throw new CouldNotCreateMollieRefundException($mollieOrderId, (string)$order->getOrderNumber());
        }

        $refundAmount = (float)$refund->amount->value;



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
     * @param RefundRequest $request
     * @param OrderEntity $order
     * @param \Mollie\Api\Resources\Order $mollieOrder
     * @return RefundItem[]
     */
    private function convertToRefundItems(RefundRequest $request, OrderEntity $order, \Mollie\Api\Resources\Order $mollieOrder): array
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
                if ($order->getDeliveries() instanceof OrderDeliveryCollection) {
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
