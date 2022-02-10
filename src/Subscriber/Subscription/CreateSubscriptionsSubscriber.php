<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber\Subscription;

use Exception;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\Subscription\SubscriptionOptions;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use Monolog\Logger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CreateSubscriptionsSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $stateMachineStateRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @var SubscriptionOptions
     */
    private $subscriptionOptions;

    /**
     * @var MollieApiClient
     */
    private $mollieApi;

    /**
     * @var EntityRepositoryInterface
     */
    private $mollieSubscriptionToProductRepository;

    /**
     * @var MollieApiFactory
     */
    private $apiFactory;

    /**
     * @var CustomerService
     */
    private $customerService;

    /**
     * @var LoggerService
     */
    private $loggerService;

    /**
     * @param EntityRepositoryInterface $stateMachineStateRepository
     * @param EntityRepositoryInterface $orderRepository
     * @param EntityRepositoryInterface $orderTransactionRepository
     * @param EntityRepositoryInterface $mollieSubscriptionToProductRepository
     * @param SubscriptionOptions $subscriptionOptions
     * @param MollieApiFactory $apiFactory
     * @param MollieApiClient $mollieApi
     * @param CustomerService $customerService
     * @param LoggerService $loggerService
     */
    public function __construct(
        EntityRepositoryInterface $stateMachineStateRepository,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderTransactionRepository,
        EntityRepositoryInterface $mollieSubscriptionToProductRepository,
        SubscriptionOptions $subscriptionOptions,
        MollieApiFactory $apiFactory,
        MollieApiClient $mollieApi,
        CustomerService $customerService,
        LoggerService $loggerService
    ) {
        $this->stateMachineStateRepository = $stateMachineStateRepository;
        $this->orderRepository = $orderRepository;
        $this->mollieSubscriptionToProductRepository = $mollieSubscriptionToProductRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->subscriptionOptions = $subscriptionOptions;
        $this->apiFactory = $apiFactory;
        $this->mollieApi = $mollieApi;
        $this->customerService = $customerService;
        $this->loggerService = $loggerService;
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'onStateTransition'
        ];
    }

    /**
     * @param StateMachineTransitionEvent $event
     * @throws ApiException
     * @throws Exception
     */
    public function onStateTransition(StateMachineTransitionEvent $event)
    {
        if ($event->getEntityName() !== OrderTransactionDefinition::ENTITY_NAME) {
            return;
        }

        $orderTransactionsStatePaidId = $this->getOrderTransactionsStatePaidId($event->getContext());
        if ($orderTransactionsStatePaidId === null) {
            return;
        }

        if ($event->getToPlace()->getId() !== $orderTransactionsStatePaidId) {

            return;
        }

        $order = $this->getOrder($event->getEntityId(), $event->getContext());

        $subscriptions = $this->subscriptionOptions->forOrder($order, $event->getEntityId());
        foreach ($subscriptions as $subscriptionOptions) {
            $customerId = $this->customerService->getMollieCustomerId(
                $order->getOrderCustomer()->getCustomerId(),
                $order->getSalesChannelId(),
                $event->getContext()
            );
            $this->createSubscription($customerId, $subscriptionOptions, $event->getContext());
        }

        $this->orderTransactionRepository->upsert([[
            'id' => $event->getEntityId(),
            'customFields' => ['subscription_created' => date('Y-m-d')]
        ]], $event->getContext());
    }

    /**
     * @param $customerId
     * @param $subscriptionOptions
     * @param $context
     * @throws IncompatiblePlatform
     */
    private function createSubscription($customerId, $subscriptionOptions, $context)
    {
        $this->loggerService->addEntry(
            'request',
            Context::createDefaultContext(),
            null,
            ['customerId' => $customerId, 'options' => $subscriptionOptions->toArray()],
            Logger::INFO
        );

        $mollieApi = $this->apiFactory->getClient($subscriptionOptions->getSalesChannelId());
        $subscription = $mollieApi->subscriptions->createForId($customerId, $subscriptionOptions->toArray());

        $this->mollieSubscriptionToProductRepository->create([
            [
                'id' => Uuid::randomHex(),
                'mollieCustomerId' => $subscription->customerId,
                'subscriptionId' => $subscription->id,
                'productId' => $subscriptionOptions->getProductId(),
                'salesChannelId' => $subscriptionOptions->getSalesChannelId(),
                'status' => $subscription->status,
                'description' => $subscription->description,
                'amount' => $subscription->amount->value,
                'currency' => $subscription->amount->currency,
                'nextPaymentDate' => $subscription->nextPaymentDate
            ]
        ], Context::createDefaultContext());
    }

    /**
     * @param Context $context
     * @return string|null
     */
    private function getOrderTransactionsStatePaidId(Context $context): ?string
    {
        $criteria = new Criteria();

        $criteria->addFilter(
            new EqualsFilter(
                'stateMachine.technicalName',
                \sprintf('%s.state', OrderTransactionDefinition::ENTITY_NAME)
            ),
            new EqualsFilter('technicalName', OrderTransactionStates::STATE_PAID)
        );

        return $this->stateMachineStateRepository->searchIds($criteria, $context)->firstId();
    }

    /**
     * @param string $orderTransactionId
     * @param Context $context
     * @return OrderEntity|null
     */
    private function getOrder(string $orderTransactionId, Context $context): ?OrderEntity
    {
        $criteria = (new Criteria())
            ->addAssociation('lineItems')
            ->addAssociation('transactions')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.positions.orderLineItem')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('currency')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        $criteria->addFilter(
            new EqualsFilter('transactions.id', $orderTransactionId)
        );

        return $this->orderRepository->search($criteria, $context)->first();
    }
}
