<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Symfony\Component\HttpFoundation\ParameterBag;

final class FakeOrderService extends OrderService
{
    public function __construct()
    {
    }

    public function createOrder(DataBag $data, SalesChannelContext $context): string
    {
    }

    public function orderStateTransition(string $orderId, string $transition, ParameterBag $data, Context $context): StateMachineStateEntity
    {
    }

    public function orderTransactionStateTransition(string $orderTransactionId, string $transition, ParameterBag $data, Context $context): StateMachineStateEntity
    {
    }

    public function orderDeliveryStateTransition(string $orderDeliveryId, string $transition, ParameterBag $data, Context $context): StateMachineStateEntity
    {
    }

    public function isPaymentChangeableByTransactionState(OrderEntity $order): bool
    {
    }
}
