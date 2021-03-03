<?php declare(strict_types=1);


namespace Kiener\MolliePayments\Validator;


use Kiener\MolliePayments\Exception\MissingPriceLineItemException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class OrderLineItemValidator
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param OrderLineItemEntity $lineItemEntity
     * @throws MissingPriceLineItemException
     */
    public function validate(OrderLineItemEntity $lineItemEntity): void
    {
        $price = $lineItemEntity->getPrice();

        if (!$price instanceof CalculatedPrice) {
            $this->logger->critical(
                sprintf(
                    'The order could not be prepared for mollie api. The LineItem with id (%s) has no prices',
                    $lineItemEntity->getId()
                )
            );

            throw new MissingPriceLineItemException($lineItemEntity->getId());
        }
    }
}
