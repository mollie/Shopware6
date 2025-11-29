<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Fakes;

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

    public function __construct(OrderEntity $orderEntity)
    {
        $this->orderEntity = $orderEntity;
    }

    public function createOrder(DataBag $data, SalesChannelContext $context): OrderEntity
    {
        return $this->orderEntity;
    }

    public function getOrder(string $orderId, Context $context): OrderEntity
    {
        return $this->orderEntity;
    }

    public function getOrderByNumber(string $orderNumber, Context $context): OrderEntity
    {
        return $this->orderEntity;
    }

    public function getMollieOrderId(OrderEntity $order): string
    {
        return $this->orderEntity->getId();
    }

    public function getMollieOrderLineId(OrderLineItemEntity $lineItem): string
    {
        return '';
    }
}
