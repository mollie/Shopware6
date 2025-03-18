<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\OrderDeliveriesNotFoundException;
use Kiener\MolliePayments\Exception\OrderDeliveryNotFoundException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class OrderDeliveryExtractor
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $loggerService)
    {
        $this->logger = $loggerService;
    }

    public function extractDeliveries(OrderEntity $orderEntity, Context $context): OrderDeliveryCollection
    {
        $deliveries = $orderEntity->getDeliveries();

        if (! $deliveries instanceof OrderDeliveryCollection) {
            $this->logger->critical(
                sprintf('Could not fetch deliveries from order with id %s', $orderEntity->getId())
            );

            throw new OrderDeliveriesNotFoundException($orderEntity->getId());
        }

        return $deliveries;
    }

    public function extractDelivery(OrderEntity $orderEntity, Context $context): OrderDeliveryEntity
    {
        $deliveries = $this->extractDeliveries($orderEntity, $context);

        /**
         * TODO: In future Shopware versions there might be multiple deliveries. There is support for multiple deliveries
         * but as of writing only one delivery is created per order, which is why we use first() here.
         */
        $delivery = $deliveries->first();

        if (! $delivery instanceof OrderDeliveryEntity) {
            $this->logger->critical(
                sprintf('Could not fetch deliveries from order with id %s', $orderEntity->getId())
            );

            throw new OrderDeliveryNotFoundException($orderEntity->getId());
        }

        return $delivery;
    }
}
