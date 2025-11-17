<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\Service;

use Kiener\MolliePayments\Components\RefundManager\RefundManagerInterface;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequestItem;
use Kiener\MolliePayments\Service\OrderService;
use Psr\Log\LoggerInterface;
use Shopware\Commercial\ReturnManagement\Entity\OrderReturn\OrderReturnEntity;
use Shopware\Commercial\ReturnManagement\Entity\OrderReturnLineItem\OrderReturnLineItemEntity;
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
    private $orderReturnRepository;
    private LoggerInterface $logger;
    private OrderService $orderService;

    private bool $featureDisabled = false;

    /**
     * @param ?EntityRepository<EntityCollection<OrderReturnEntity>> $orderReturnRepository
     */
    public function __construct(
        RefundManagerInterface $refundManager,
        $orderReturnRepository,
        OrderService $orderService,
        LoggerInterface $logger
    ) {
        $this->refundManager = $refundManager;
        $this->orderReturnRepository = $orderReturnRepository;
        $this->logger = $logger;
        $this->orderService = $orderService;
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
        $request = $this->createRequestFromOrder((string) $order->getOrderNumber(), $orderReturn);
        $order = $this->orderService->getOrder($order->getId(), $context); // need to load the order again because the line items are not loaded in the event
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

    private function createRequestFromOrder(string $orderNumber, OrderReturnEntity $orderReturn): RefundRequest
    {
        $request = new RefundRequest(
            $orderNumber,
            (string) $orderReturn->getInternalComment(),
            '',
            $orderReturn->getAmountTotal()
        );
        /** @var OrderReturnLineItemEntity $item */
        foreach ($orderReturn->getLineItems() as $item) {
            $price = $item->getRefundAmount() / $item->getQuantity();
            $refundRequestItem = new RefundRequestItem($item->getOrderLineItemId(), $price, $item->getQuantity(), 0);
            $request->addItem($refundRequestItem);
        }

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
