<?php declare(strict_types=1);


namespace Kiener\MolliePayments\Service\Transition;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface OrderTransitionServiceInterface
{
    public function openOrder(OrderEntity $order, Context $context): void;

    public function processOrder(OrderEntity $order, Context $context): void;

    public function completeOrder(OrderEntity $order, Context $context): void;

    public function cancelOrder(OrderEntity $order, Context $context): void;
}
