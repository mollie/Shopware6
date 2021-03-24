<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;


use Kiener\MolliePayments\Exception\MissingOrderInTransactionException;
use Kiener\MolliePayments\Service\ChangeTransactionStatus;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MolliePaymentExtractor;
use Mollie\Api\Resources\Order as MollieOrder;
use Monolog\Logger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MollieChangeTransactionStatus
{
    /**
     * @var EntityRepositoryInterface
     */
    private $transactionRepository;
    /**
     * @var LoggerService
     */
    private $logger;
    /**
     * @var MolliePaymentExtractor
     */
    private $extractor;
    /**
     * @var Order
     */
    private $mollieOrderService;
    /**
     * @var ChangeTransactionStatus
     */
    private $changeTransactionStatus;

    public function __construct(
        EntityRepositoryInterface $transactionRepository,
        LoggerService $logger,
        MolliePaymentExtractor $extractor,
        Order $mollieOrderService,
        ChangeTransactionStatus $changeTransactionStatus
    )
    {

        $this->transactionRepository = $transactionRepository;
        $this->logger = $logger;
        $this->extractor = $extractor;
        $this->mollieOrderService = $mollieOrderService;
        $this->changeTransactionStatus = $changeTransactionStatus;
    }


    public function changePaymentStatus(string $transactionId, SalesChannelContext $salesChannelContext): void
    {
        $context = $salesChannelContext->getContext();
        $order = $this->getOrderEntity($transactionId, $context);

        if (!$order instanceof OrderEntity) {
            return;
        }

        //get last transaction of order only if it is a mollie transaction
        $transaction = $this->extractor->extractLast($order->getTransactions());

        if (!$transaction instanceof OrderTransactionEntity || $transactionId !== $transaction->getId()) {
            $this->logger->addEntry(
                'Webhook has been called for a transaction that is not the last order transaction.',
                $context,
                null,
                null,
                Logger::DEBUG
            );

            return;
        }

        $customFields = $order->getCustomFields();
        $mollieOrderId = $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY] ?? null;

        if (empty($mollieOrderId)) {
            $this->logger->addEntry(
                sprintf(
                    'An order for transaction (%s) has been found. A valid mollieOrder ID could not be found',
                    $transactionId
                ),
                $context,
                null,
                null,
                Logger::DEBUG
            );

            return;
        }

        $usedSalesChannelContext = $order->getSalesChannel() ?? $salesChannelContext;

        $mollieOrder = $this->mollieOrderService->getOrder($mollieOrderId, $usedSalesChannelContext);

        if (!$mollieOrder instanceof MollieOrder) {
            $this->logger->addEntry(
                sprintf(
                    'We couldn\'t fetch an mollie order from api for identifier %s',
                    $mollieOrderId
                ),
                $context,
                null,
                null,
                Logger::ERROR
            );

            return;
        }


    }

    private function getOrderEntity(string $transactionId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('order', 'order.transactions');

        $searchResult = $this->transactionRepository->search($criteria, $context);

        if ($searchResult->count() === 0) {
            $this->logger->addEntry(
                sprintf('A transaction with id %s could not be found.', $transactionId),
                $context,
                null,
                null,
                Logger::WARNING
            );

            return null;
        }

        /** @var OrderTransactionEntity $transaction */
        $transaction = $searchResult->first();

        $order = $transaction->getOrder();

        if (!$order instanceof OrderEntity) {
            $this->logger->addEntry(
                sprintf('Could not find an order for transaction with id %s.', $transactionId),
                $context,
                null,
                null,
                Logger::CRITICAL
            );

            throw new MissingOrderInTransactionException($transactionId);
        }

        return $transaction;
    }
}
