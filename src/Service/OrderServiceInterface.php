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

interface OrderServiceInterface
{

    /**
     * @param DataBag $data
     * @param SalesChannelContext $context
     * @return OrderEntity
     */
    public function createOrder(DataBag $data, SalesChannelContext $context): OrderEntity;

    /**
     * @param string $orderId
     * @param Context $context
     * @return OrderEntity
     */
    public function getOrder(string $orderId, Context $context): OrderEntity;

    /**
     * @param string $orderNumber
     * @param Context $context
     * @return OrderEntity
     */
    public function getOrderByNumber(string $orderNumber, Context $context): OrderEntity;

    /**
     * @param OrderEntity $order
     * @return string
     */
    public function getMollieOrderId(OrderEntity $order): string;

    /**
     * @param OrderLineItemEntity $lineItem
     * @return string
     */
    public function getMollieOrderLineId(OrderLineItemEntity $lineItem): string;

}
