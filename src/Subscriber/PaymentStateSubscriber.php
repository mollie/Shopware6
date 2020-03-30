<?php

namespace Kiener\MolliePayments\Subscriber;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentStateSubscriber implements EventSubscriberInterface
{
    /** @var MollieApiClient $apiClient */
    private $apiClient;

    /** @var EntityRepositoryInterface $orderTransactionRepository */
    private $orderTransactionRepository;

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     * * The method name to call (priority defaults to 0)
     * * An array composed of the method name to call and the priority
     * * An array of arrays composed of the method names to call and respective
     *   priorities, or 0 if unset
     *
     * For instance:
     *
     * * array('eventName' => 'methodName')
     * * array('eventName' => array('methodName', $priority))
     * * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            OrderEvents::ORDER_TRANSACTION_WRITTEN_EVENT => 'onOrderTransactionWritten'
        ];
    }

    /**
     * Creates a new instance of PaymentMethodSubscriber.
     *
     * @param MollieApiClient $apiClient
     * @param EntityRepositoryInterface $orderTransactionRepository
     * @param EntityRepositoryInterface $stateMachineStateRepository
     */
    public function __construct(
        MollieApiClient $apiClient,
        EntityRepositoryInterface $orderTransactionRepository
    )
    {
        $this->apiClient = $apiClient;
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    /**
     * Refunds the transaction at Mollie if the payment state is refunded.
     *
     * @param EntityWrittenEvent $args
     * @throws ApiException
     */
    public function onOrderTransactionWritten(EntityWrittenEvent $args): void
    {
        foreach ($args->getPayloads() as $payload) {
            $transactionId = $payload['id'];
            $transactionVersionId = $payload['versionId'];
            $order = null;
            $customFields = null;
            $mollieOrder = null;

            try {
                /** @var OrderTransactionEntity $transaction */
                $transaction = $this->getTransaction($transactionId, $transactionVersionId);
            } catch (InconsistentCriteriaIdsException $e) {
                // @todo Handle exception
            }

            // Get the order from the transaction
            if (
                $transaction !== null
                && $transaction->getStateMachineState() !== null
                && $transaction->getStateMachineState()->getTechnicalName() === 'refunded'
            ) {
                $order = $transaction->getOrder();
            }

            // Get the custom fields from the order
            if (
                $order !== null
                && $order->getCustomFields() !== null
            ) {
                $customFields = $order->getCustomFields();
            }

            // Get the order at Mollie
            if (
                $customFields !== null
                && isset($customFields['mollie_payments']['order_id'])
            ) {
                $mollieOrder = $this->apiClient->orders->get(
                    $customFields['mollie_payments']['order_id']
                );
            }

            // Refund the order
            if ($mollieOrder !== null) {
                $mollieOrder->refundAll();
            }
        }
    }

    /**
     * Finds a transaction by id.
     *
     * @param $transactionId
     * @param $versionId
     * @return OrderTransactionEntity|null
     * @throws InconsistentCriteriaIdsException
     */
    private function getTransaction($transactionId, $versionId): ?OrderTransactionEntity
    {
        $transactionCriteria = new Criteria();
        $transactionCriteria->addFilter(new EqualsFilter('id', $transactionId));
        $transactionCriteria->addFilter(new EqualsFilter('versionId', $versionId));
        $transactionCriteria->addAssociation('order');

        /** @var OrderTransactionCollection $transactions */
        $transactions = $this->orderTransactionRepository->search($transactionCriteria, Context::createDefaultContext());

        if ($transactions->count() === 0) {
            return null;
        }

        return $transactions->first();
    }
}