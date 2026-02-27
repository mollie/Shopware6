<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\StateHandler;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface OrderStateHandlerInterface
{
    public function performTransition(OrderEntity $shopwareOrder, string $shopwarePaymentStatus, string $currentState,string $salesChannelId, Context $context): string;
}
