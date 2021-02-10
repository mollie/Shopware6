<?php declare(strict_types=1);


namespace Kiener\MolliePayments\Validator;


use Kiener\MolliePayments\Exception\MissingPriceLineItemException;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class OrderLineItemValidator
{
    /**
     * @param OrderLineItemEntity $lineItemEntity
     * @throws MissingPriceLineItemException
     */
    public function validate(OrderLineItemEntity $lineItemEntity): void
    {
        $price = $lineItemEntity->getPrice();

        if (!$price instanceof CalculatedPrice) {
            throw new MissingPriceLineItemException($lineItemEntity->getId());
        }
    }
}
