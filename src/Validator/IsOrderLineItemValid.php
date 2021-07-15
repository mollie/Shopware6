<?php declare(strict_types=1);


namespace Kiener\MolliePayments\Validator;


use Kiener\MolliePayments\Exception\MissingPriceLineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class IsOrderLineItemValid
{
    /**
     * @param OrderLineItemEntity $lineItemEntity
     * @throws MissingPriceLineItem
     */
    public function validate(OrderLineItemEntity $lineItemEntity): void
    {
        if ($lineItemEntity->getPrice() instanceof CalculatedPrice) {

            return;
        }

        throw new MissingPriceLineItem($lineItemEntity->getId());
    }
}
