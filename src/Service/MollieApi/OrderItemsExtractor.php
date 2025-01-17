<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\OrderLineItemsNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;

class OrderItemsExtractor
{

    /**
     * @param OrderEntity $orderEntity
     * @return OrderLineItemCollection
     */
    public function extractLineItems(OrderEntity $orderEntity): OrderLineItemCollection
    {
        $lineItems = $orderEntity->getLineItems();

        if (!$lineItems instanceof OrderLineItemCollection) {
            throw new OrderLineItemsNotFoundException($orderEntity->getId());
        }

        return $lineItems;
    }
}
