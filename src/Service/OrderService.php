<?php

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderIdException;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;


class OrderService
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $orderLineItemRepository;

    /**
     * @var \Shopware\Core\Checkout\Order\SalesChannel\OrderService
     */
    private $swOrderService;

    /**
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * @param EntityRepositoryInterface $orderRepository
     * @param EntityRepositoryInterface $orderLineItemRepository
     * @param \Shopware\Core\Checkout\Order\SalesChannel\OrderService $swOrderService
     * @param LoggerInterface $logger
     */
    public function __construct(EntityRepositoryInterface $orderRepository, EntityRepositoryInterface $orderLineItemRepository, \Shopware\Core\Checkout\Order\SalesChannel\OrderService $swOrderService, LoggerInterface $logger)
    {
        $this->orderRepository = $orderRepository;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->swOrderService = $swOrderService;
        $this->logger = $logger;
    }


    /**
     * Returns the order repository.
     *
     * @return EntityRepositoryInterface
     */
    public function getOrderRepository()
    {
        return $this->orderRepository;
    }

    /**
     * Returns the order line item repository.
     *
     * @return EntityRepositoryInterface
     */
    public function getOrderLineItemRepository()
    {
        return $this->orderLineItemRepository;
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
    public function getOrder(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('currency');
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('language');
        $criteria->addAssociation('language.locale');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('lineItems.product');
        $criteria->addAssociation('lineItems.product.media');
        $criteria->addAssociation('deliveries');
        $criteria->addAssociation('deliveries.shippingOrderAddress');
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('transactions.paymentMethod');

        /** @var OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if ($order instanceof OrderEntity) {
            return $order;
        }

        $this->logger->critical(
            sprintf('Could not find an order with id %s. Payment failed', $orderId)
        );

        throw new OrderNotFoundException($orderId);
    }

    /**
     * @param OrderEntity $order
     * @return string
     * @throws CouldNotExtractMollieOrderIdException
     */
    public function getMollieOrderId(OrderEntity $order): string
    {
        $mollieOrderId = $order->getCustomFields()['mollie_payments']['order_id'] ?? '';

        if (empty($mollieOrderId)) {
            throw new CouldNotExtractMollieOrderIdException($order->getOrderNumber());
        }

        return $mollieOrderId;
    }

}
