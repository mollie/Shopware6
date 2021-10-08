<?php

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderIdException;
use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderLineIdException;
use Kiener\MolliePayments\Exception\OrderNumberNotFoundException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService as ShopwareOrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OrderService
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var ShopwareOrderService
     */
    private $swOrderService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param EntityRepositoryInterface $orderRepository
     * @param ShopwareOrderService $swOrderService
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityRepositoryInterface $orderRepository,
        ShopwareOrderService $swOrderService,
        LoggerInterface $logger
    )
    {
        $this->orderRepository = $orderRepository;
        $this->swOrderService = $swOrderService;
        $this->logger = $logger;
    }

    /**
     * @param DataBag $data
     * @param SalesChannelContext $context
     * @return OrderEntity
     */
    public function createOrder(DataBag $data, SalesChannelContext $context): OrderEntity
    {
        $orderId = $this->swOrderService->createOrder($data, $context);

        $order = $this->getOrder($orderId, $context->getContext());

        if (!$order instanceof OrderEntity) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }

    /**
     * Return an order entity, enriched with associations.
     *
     * @param string $orderId
     * @param Context $context
     * @return OrderEntity|null
     */
    public function getOrder(string $orderId, Context $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('currency');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('language.locale');
        $criteria->addAssociation('lineItems.product.media');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('transactions.paymentMethod');

        /** @var OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if ($order instanceof OrderEntity) {
            return $order;
        }

        $this->logger->critical(
            sprintf('Could not find an order with id %s.', $orderId)
        );

        throw new OrderNotFoundException($orderId);
    }

    public function getOrderByNumber(string $orderNumber, Context $context): OrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        $orderId = $this->orderRepository->searchIds($criteria, $context)->firstId();

        if(is_string($orderId)) {
            return $this->getOrder($orderId, $context);
        }

        $this->logger->critical(
            sprintf('Could not find an order with order number %s.', $orderNumber)
        );

        throw new OrderNumberNotFoundException($orderNumber);
    }

    /**
     * @param OrderEntity $order
     * @return string
     * @throws CouldNotExtractMollieOrderIdException
     */
    public function getMollieOrderId(OrderEntity $order): string
    {
        $mollieOrderId = $order->getCustomFields()[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY] ?? '';

        if (empty($mollieOrderId)) {
            throw new CouldNotExtractMollieOrderIdException($order->getOrderNumber());
        }

        return $mollieOrderId;
    }

    public function getMollieOrderLineId(OrderLineItemEntity $lineItem): string
    {
        $mollieOrderLineId = $lineItem->getCustomFields()[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_LINE_KEY] ?? '';

        if (empty($mollieOrderLineId)) {
            throw new CouldNotExtractMollieOrderLineIdException($lineItem->getId());
        }

        return $mollieOrderLineId;
    }
}
