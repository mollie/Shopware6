<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\Service;

use Kiener\MolliePayments\Components\RefundManager\RefundManagerInterface;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequestItem;
use Psr\Log\LoggerInterface;
use Shopware\Commercial\ReturnManagement\Entity\OrderReturn\OrderReturnEntity;
use Shopware\Commercial\ReturnManagement\Entity\OrderReturnLineItem\OrderReturnLineItemEntity;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderReturnHandler
{
    private RefundManagerInterface $refundManager;

    /**
     * @var ?EntityRepository<EntityCollection<OrderReturnEntity>>
     */
    private ?EntityRepository $orderReturnRepository;
    private LoggerInterface $logger;

    private bool $featureDisabled = false;

    /**
     * @param ?EntityRepository<EntityCollection<OrderReturnEntity>> $orderReturnRepository
     */
    public function __construct(
        RefundManagerInterface $refundManager,
        ?EntityRepository $orderReturnRepository,
        LoggerInterface $logger
    ) {
        $this->refundManager = $refundManager;
        $this->orderReturnRepository = $orderReturnRepository;
        $this->logger = $logger;
        $this->featureDisabled = $orderReturnRepository === null;
    }

    public function return(OrderEntity $order, Context $context): void
    {
        if ($this->featureDisabled) {
            return;
        }
        $orderReturn = $this->findReturnByOrder($order, $context);
        if ($orderReturn === null) {
            return;
        }

        $order = $orderReturn->getOrder();
        if (! $order instanceof OrderEntity) {
            $this->logger->error('Order Return has no order associated', [
                'returnId' => $orderReturn->getId()
            ]);

            return;
        }
        $request = $this->createRequestFromOrder($order, $orderReturn);

        try {
            $this->refundManager->refund($order, $request, $context);
        } catch (\Throwable $throwable) {
            $this->logger->error('Error during refund status change: {{message}}', ['message' => $throwable->getMessage()]);
        }
    }

    public function cancel(OrderEntity $order, Context $context): void
    {
        if ($this->featureDisabled) {
            return;
        }
        $this->refundManager->cancelAllOrderRefunds($order, $context);
    }

    private function createRequestFromOrder(OrderEntity $order, OrderReturnEntity $orderReturn): RefundRequest
    {
        $amount = (float) $orderReturn->getAmountTotal();
        $orderNumber = $order->getOrderNumber();

        $request = new RefundRequest(
            $orderNumber,
            (string) $orderReturn->getInternalComment(),
            '',
            $amount
        );
        /** @var OrderReturnLineItemEntity $item */
        foreach ($orderReturn->getLineItems() as $item) {
            $price = $item->getRefundAmount() / $item->getQuantity();
            $refundRequestItem = new RefundRequestItem($item->getOrderLineItemId(), $price, $item->getQuantity(), 0);
            $request->addItem($refundRequestItem);
        }

        $orderShippingTotal = $order->getShippingTotal();
        if ($orderShippingTotal <= 0) {
            return $request;
        }

        $shippingCosts = $orderReturn->getShippingCosts();
        $shippingCostsValue = 0.0;
        if ($shippingCosts instanceof CalculatedPrice) {
            $shippingCostsValue = $shippingCosts->getTotalPrice();
        }

        $isFullReturn = ($orderReturn->getAmountTotal() ?? 0.0) + $orderShippingTotal === $order->getAmountTotal();

        if ($isFullReturn && $shippingCostsValue === 0.0) {
            return $this->addOrderDeliveryToRefundRequest($request, $order);
        }

        $deliveries = $order->getDeliveries();
        if (! $deliveries instanceof OrderDeliveryCollection) {
            return $request;
        }
        $refundRequestItem = null;

        foreach ($deliveries as $delivery) {
            $shippingCosts = $delivery->getShippingCosts();
            if ($shippingCosts->getTotalPrice() > 0) {
                $refundRequestItem = new RefundRequestItem(
                    $delivery->getId(),
                    $shippingCostsValue,
                    $shippingCosts->getQuantity(),
                    0
                );
                $request->addItem($refundRequestItem);
                break;
            }
        }

        return $request;
    }

    private function addOrderDeliveryToRefundRequest(RefundRequest $request, OrderEntity $order): RefundRequest
    {
        $deliveries = $order->getDeliveries();
        if (! $deliveries instanceof OrderDeliveryCollection) {
            return $request;
        }
        $amount = (float) $request->getAmount();
        foreach ($deliveries as $delivery) {
            $shippingCosts = $delivery->getShippingCosts();
            if ($shippingCosts->getTotalPrice() < 0) {
                continue;
            }
            $amount += $shippingCosts->getTotalPrice();

            $refundRequestItem = new RefundRequestItem(
                $delivery->getId(),
                $shippingCosts->getTotalPrice(),
                $shippingCosts->getQuantity(),
                0
            );
            $request->addItem($refundRequestItem);
        }
        $request->setAmount($amount);

        return $request;
    }

    private function findReturnByOrder(OrderEntity $order, Context $context): ?OrderReturnEntity
    {
        if ($this->orderReturnRepository === null) {
            return null;
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $order->getId()));
        $criteria->addFilter(new EqualsFilter('orderVersionId', $order->getVersionId()));
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('order.deliveries');
        $criteria->addAssociation('order.deliveries.shippingCosts');
        $criteria->addAssociation('order.deliveries.shippingMethod');
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('order.currency');

        $orderReturnSearchResult = $this->orderReturnRepository->search($criteria, $context);

        if ($orderReturnSearchResult->getTotal() === 0) {
            $this->logger->warning('Failed to find order return for order {{orderNumber}}', [
                'orderNumber' => $order->getOrderNumber(),
            ]);

            return null;
        }

        /* @var OrderReturnEntity $orderReturn */
        return $orderReturnSearchResult->first();
    }
}
