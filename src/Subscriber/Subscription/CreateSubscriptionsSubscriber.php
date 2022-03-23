<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber\Subscription;

use Exception;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\Subscription\DTO\SubscriptionOption;
use Kiener\MolliePayments\Service\Subscription\SubscriptionOptions;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
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
     * @var LoggerInterface
     */
    private $loggerService;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @param EntityRepositoryInterface $stateMachineStateRepository
     * @param EntityRepositoryInterface $orderRepository
     * @param EntityRepositoryInterface $orderTransactionRepository
     * @param EntityRepositoryInterface $mollieSubscriptionToProductRepository
     * @param SubscriptionOptions $subscriptionOptions
     * @param MollieApiFactory $apiFactory
     * @param CustomerService $customerService
     * @param LoggerInterface $loggerService
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(
        EntityRepositoryInterface $stateMachineStateRepository,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderTransactionRepository,
        EntityRepositoryInterface $mollieSubscriptionToProductRepository,
        SubscriptionOptions       $subscriptionOptions,
        MollieApiFactory          $apiFactory,
        CustomerService           $customerService,
        LoggerInterface           $loggerService,
        SystemConfigService       $systemConfigService
    )
    {
        $this->stateMachineStateRepository = $stateMachineStateRepository;
        $this->orderRepository = $orderRepository;
        $this->mollieSubscriptionToProductRepository = $mollieSubscriptionToProductRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->subscriptionOptions = $subscriptionOptions;
        $this->apiFactory = $apiFactory;
        $this->customerService = $customerService;
        $this->loggerService = $loggerService;
        $this->systemConfigService = $systemConfigService;
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

        if (!$this->systemConfigService->get('MolliePayments.config.enableSubscriptions')) {
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
        if (!$order instanceof OrderEntity) {
            return;
        }
        $originalOrderId = $order->getId();

        $customerId = $this->customerService->getMollieCustomerId(
            $order->getOrderCustomer()->getCustomerId(),
            $order->getSalesChannelId(),
            $event->getContext()
        );

        $subscriptions = $this->subscriptionOptions->forOrder($order, $event->getEntityId());
        foreach ($subscriptions as $subscriptionOptions) {
            $this->createSubscription($customerId, $originalOrderId, $subscriptionOptions, $event->getContext());
        }

        $this->orderTransactionRepository->upsert([[
            'id' => $event->getEntityId(),
            'customFields' => ['subscription_created' => date('Y-m-d')]
        ]], $event->getContext());
    }

    /**
     * @param $customerId
     * @param string $customerId
     * @param SubscriptionOption $subscriptionOptions
     * @param $context
     * @throws IncompatiblePlatform
     */
    private function createSubscription($customerId, $originalOrderId, $subscriptionOptions, $context)
    {
        $this->loggerService->info(
            'request',
            ['customerId' => $customerId, 'options' => $subscriptionOptions->toArray()]
        );

        $mollieApi = $this->apiFactory->getClient($subscriptionOptions->getSalesChannelId());
        $subscription = $mollieApi->subscriptions->createForId($customerId, $subscriptionOptions->toArray());

        $this->mollieSubscriptionToProductRepository->create([
            [
                'id' => $subscriptionOptions->getSubscriptionId(),
                'mollieCustomerId' => $subscription->customerId,
                'subscriptionId' => $subscription->id,
                'productId' => $subscriptionOptions->getProductId(),
                'originalOrderId' => $originalOrderId,
                'salesChannelId' => $subscriptionOptions->getSalesChannelId(),
                'status' => $subscription->status,
                'description' => $subscription->description,
                'amount' => $subscription->amount->value,
                'currency' => $subscription->amount->currency,
                'nextPaymentDate' => $subscription->nextPaymentDate
            ]
        ], $context);
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
