<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MolliePaymentExtractor;
use Kiener\MolliePayments\Service\OrderDeliveryService;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Bridge\Monolog\Logger;

class MollieShipment
{
    /**
     * @var MolliePaymentExtractor
     */
    private $extractor;

    /**
     * @var Order
     */
    private $mollieApiOrderService;

    /**
     * @var OrderDeliveryService
     */
    private $orderDeliveryService;

    /**
     * @var LoggerService
     */
    private $logger;

    public function __construct(
        MolliePaymentExtractor $extractor,
        Order $mollieApiOrderService,
        OrderDeliveryService $orderDeliveryService,
        LoggerService $logger
    )
    {
        $this->extractor = $extractor;
        $this->mollieApiOrderService = $mollieApiOrderService;
        $this->orderDeliveryService = $orderDeliveryService;
        $this->logger = $logger;
    }

    public function setShipment(string $orderDeliveryId, Context $context): bool
    {
        $delivery = $this->orderDeliveryService->getDelivery($orderDeliveryId, $context);

        if (!$delivery instanceof OrderDeliveryEntity) {
            $this->logger->addEntry(
                sprintf('Order delivery with id %s could not be found in database', $orderDeliveryId),
                null,
                null,
                Logger::WARNING
            );

            return false;
        }

        $order = $delivery->getOrder();

        if (!$order instanceof OrderEntity) {
            $this->logger->addEntry(
                sprintf('Loaded delivery with id %s does not have an order in database', $orderDeliveryId),
                null,
                null,
                Logger::WARNING
            );

            return false;
        }

        $customFields = $order->getCustomFields();
        $mollieOrderId = $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY] ?? null;

        if (!$mollieOrderId) {
            $this->logger->addEntry(
                sprintf('Mollie orderId does not exist in shopware order (%s)', (string)$order->getOrderNumber()),
                null,
                null,
                Logger::WARNING
            );

            return false;
        }

        // get last transaction if it is a mollie transaction
        $lastTransaction = $this->extractor->extractLast($order->getTransactions());

        if (!$lastTransaction instanceof OrderTransactionEntity) {
            $this->logger->addEntry(
                sprintf(
                    'The last transaction of the order (%s) is not a mollie payment! No shipment will be sent to mollie',
                    (string)$order->getOrderNumber()
                ),
                null,
                null,
                Logger::INFO
            );

            return false;
        }

        $addedMollieShipment = $this->mollieApiOrderService->setShipment($mollieOrderId, $order->getSalesChannelId(),$context);

        if ($addedMollieShipment) {
            $values = [CustomFieldsInterface::DELIVERY_SHIPPED => true];
            $this->orderDeliveryService->updateCustomFields($delivery, $values, $context);
        }

        return $addedMollieShipment;
    }
}
