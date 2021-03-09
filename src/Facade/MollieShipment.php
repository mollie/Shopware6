<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MolliePaymentExtractor;
use Kiener\MolliePayments\Service\OrderDeliveryService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

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
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        MolliePaymentExtractor $extractor,
        Order $mollieApiOrderService,
        OrderDeliveryService $orderDeliveryService,
        LoggerInterface $logger
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
            $this->logger->warning(
                sprintf('Order delivery with id %s could not be found in database', $orderDeliveryId)
            );

            return false;
        }

        $order = $delivery->getOrder();

        if (!$order instanceof OrderEntity) {
            $this->logger->warning(
                sprintf('Loaded delivery with id %s does not have an order in database', $orderDeliveryId)
            );

            return false;
        }

        $customFields = $order->getCustomFields();
        $mollieOrderId = $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY] ?? null;

        if (!$mollieOrderId) {
            $this->logger->warning(
                sprintf('Mollie orderId does not exist in shopware order (%s)', (string)$order->getOrderNumber())
            );

            return false;
        }

        // get last transaction if it is a mollie transaction
        $lastTransaction = $this->extractor->extractLast($order->getTransactions());

        if (!$lastTransaction instanceof OrderTransactionEntity) {
            $this->logger->info(
                sprintf(
                    'The last transaction of the order (%s) is not a mollie payment! No shipment will be sent to mollie',
                    (string)$order->getOrderNumber()
                )
            );

            return false;
        }

        $addedMollieShipment = $this->mollieApiOrderService->setShipment($mollieOrderId, $order->getSalesChannelId());

        if ($addedMollieShipment) {
            $values = [CustomFieldsInterface::DELIVERY_SHIPPED => true];
            $this->orderDeliveryService->updateCustomFields($delivery, $values, $context);
        }

        return $addedMollieShipment;
    }
}
