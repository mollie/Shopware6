<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Exception\MollieRefundException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\TransactionService;
use Mollie\Api\Exceptions\ApiException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class SetMollieOrderRefunded
{
    /**
     * @var Order
     */
    private $mollieOrderService;
    /**
     * @var MollieApiFactory
     */
    private $apiFactory;
    /**
     * @var TransactionService
     */
    private $transactionService;

    public function __construct(TransactionService $transactionService, Order $mollieOrderService, MollieApiFactory $apiFactory)
    {
        $this->transactionService = $transactionService;
        $this->mollieOrderService = $mollieOrderService;
        $this->apiFactory = $apiFactory;
    }

    /**
     * @param string $orderTransactionId
     * @param Context $context
     * @throws MollieRefundException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function setRefunded(string $orderTransactionId, Context $context): void
    {
        $transaction = $this->transactionService->getTransactionById($orderTransactionId, null, $context);

        if (!$transaction instanceof OrderTransactionEntity) {
            throw new MollieRefundException(
                sprintf('Could not find transaction %s ', $orderTransactionId)
            );
        }

        $order = $transaction->getOrder();

        if (!$order instanceof OrderEntity) {
            throw new MollieRefundException(
                sprintf('Could not find order for transaction %s ', $transaction->getId())
            );
        }

        $customFields = $order->getCustomFields() ?? [];

        $mollieOrderId = $customFields['mollie_payments']['order_id'] ?? '';

        if (empty($mollieOrderId)) {
            throw new MollieRefundException(
                sprintf('Could not find a mollie order id in order %s for transaction %s ',
                    $order->getOrderNumber(),
                    $transaction->getId()
                )
            );
        }

        $apiClient = $this->apiFactory->getClient($order->getSalesChannelId(), $context);

        try {
            $mollieOrder = $apiClient->orders->get($mollieOrderId);
            $mollieOrder->refundAll();
        } catch (ApiException $e) {
            throw new MollieRefundException(
                sprintf('Could not refund at mollie for transaction %s with mollieOrderId %s',
                    $orderTransactionId,
                    $mollieOrderId
                ),
                0,
                $e
            );
        }
    }


}
