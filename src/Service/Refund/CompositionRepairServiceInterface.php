<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Refund;

use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface CompositionRepairServiceInterface
{
    public function updateRefundItems(Refund $refund, OrderEntity $order, Context $context): OrderEntity;
}
