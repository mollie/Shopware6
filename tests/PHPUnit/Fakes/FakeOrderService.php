<?php

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Service\OrderServiceInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FakeOrderService implements OrderServiceInterface
{
    /**
     * @var OrderEntity
     */
    private $orderEntity;


    /**
     * @param OrderEntity $orderEntity
     */
    public function __construct(OrderEntity $orderEntity)
    {
        $this->orderEntity = $orderEntity;
    }


    /**
     * @param DataBag $data
     * @param SalesChannelContext $context
     * @return OrderEntity
     */
    public function createOrder(DataBag $data, SalesChannelContext $context): OrderEntity
    {
        return $this->orderEntity;
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return OrderEntity
     */
    public function getOrder(string $orderId, Context $context): OrderEntity
    {
        return $this->orderEntity;
    }

    /**
     * @param string $orderNumber
     * @param Context $context
     * @return OrderEntity
     */
    public function getOrderByNumber(string $orderNumber, Context $context): OrderEntity
    {
        return $this->orderEntity;
    }

    /**
     * @param OrderEntity $order
     * @return string
     */
    public function getMollieOrderId(OrderEntity $order): string
    {
        return $this->orderEntity->getId();
    }

    /**
     * @param OrderLineItemEntity $lineItem
     * @return string
     */
    public function getMollieOrderLineId(OrderLineItemEntity $lineItem): string
    {
        return '';
    }
}
