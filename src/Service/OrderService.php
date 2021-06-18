<?php

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Validator\IsOrderTotalRoundingActivated;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class OrderService
{
    public const ORDER_LINE_ITEM_ID = 'orderLineItemId';

    /** @var EntityRepositoryInterface */
    protected $orderRepository;

    /** @var EntityRepositoryInterface */
    protected $orderLineItemRepository;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * @var IsOrderTotalRoundingActivated
     */
    private $validator;

    /**
     * @var string
     */
    private $shopwareVersion;

    public function __construct(
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderLineItemRepository,
        LoggerInterface $logger,
        IsOrderTotalRoundingActivated $validator,
        string $shopwareVersion
    )
    {
        $this->orderRepository = $orderRepository;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->logger = $logger;
        $this->validator = $validator;
        $this->shopwareVersion = $shopwareVersion;
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
            sprintf('Could not find an order with id %s. Payment failed', $orderId),
            $context
        );

        throw new OrderNotFoundException($orderId);
    }
}
