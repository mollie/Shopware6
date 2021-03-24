<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;


use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Types\OrderStatus;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class GetShopwareTransactionStatus
{


    public function getStatus(MollieOrder $mollieOrder): string
    {
        $mollieStatus = $mollieOrder->status;

        switch ($mollieStatus) {
            case OrderStatus::STATUS_CREATED:
                return OrderTransactionStates::STATE_IN_PROGRESS;
            case OrderStatus::STATUS_CANCELED:
                return OrderTransactionStates::STATE_CANCELLED;
        }
    }
}
