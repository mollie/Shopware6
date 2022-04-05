<?php

namespace Kiener\MolliePayments\Components\Subscription;

use DateTime;
use Exception;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactoryInterface;
use Kiener\MolliePayments\Components\Subscription\DAL\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\MollieDataBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\SubscriptionBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionReminder\ReminderValidator;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionRenewing\SubscriptionRenewing;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SubscriptionManager implements SubscriptionManagerInterface
{

    /**
     * @var SettingsService
     */
    private $pluginSettings;

    /**
     * @var SubscriptionBuilder
     */
    private $subscriptionBuilder;

    /**
     * @var MollieDataBuilder
     */
    private $mollieRequestBuilder;

    /**
     * @var SubscriptionRepository
     */
    private $repoSubscriptions;

    /**
     * @var EntityRepositoryInterface
     */
    private $repoSalesChannel;

    /**
     * @var MollieGatewayInterface
     */
    private $gwMollie;

    /**
     * @var ReminderValidator
     */
    private $reminderValidator;

    /**
     * @var CustomerService
     */
    private $customerService;

    /**
     * @var SubscriptionRenewing
     */
    private $renewingService;

    /**
     * @var FlowBuilderDispatcherAdapterInterface
     */
    private $flowBuilderDispatcher;

    /**
     * @var FlowBuilderEventFactory
     */
    private $flowBuilderEventFactory;


    /**
     * @param FlowBuilderFactoryInterface $flowBuilderFactory
     * @param FlowBuilderEventFactory $flowBuilderEventFactory
     * @param SubscriptionRepository $repoSubscriptions
     * @param SettingsService $settingsService
     * @param MollieDataBuilder $definitionBuilder
     * @param SubscriptionBuilder $subscriptionBuilder
     * @param CustomerService $customers
     * @param MollieGatewayInterface $gatewayMollie
     * @param SubscriptionRenewing $renewingService
     * @param EntityRepositoryInterface $repoSalesChannel
     */
    public function __construct(FlowBuilderFactoryInterface $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory, SubscriptionRepository $repoSubscriptions, SettingsService $settingsService, MollieDataBuilder $definitionBuilder, SubscriptionBuilder $subscriptionBuilder, CustomerService $customers, MollieGatewayInterface $gatewayMollie, SubscriptionRenewing $renewingService, EntityRepositoryInterface $repoSalesChannel)
    {
        $this->pluginSettings = $settingsService;
        $this->repoSubscriptions = $repoSubscriptions;
        $this->mollieRequestBuilder = $definitionBuilder;
        $this->subscriptionBuilder = $subscriptionBuilder;
        $this->customerService = $customers;
        $this->gwMollie = $gatewayMollie;
        $this->renewingService = $renewingService;
        $this->repoSalesChannel = $repoSalesChannel;

        $this->flowBuilderEventFactory = $flowBuilderEventFactory;
        $this->flowBuilderDispatcher = $flowBuilderFactory->createDispatcher();

        $this->reminderValidator = new ReminderValidator();
    }

    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $context
     * @return string
     * @throws Exception
     */
    public function createSubscription(OrderEntity $order, SalesChannelContext $context): string
    {
        if ($order->getLineItems()->count() > 1) {
            # Mixed carts are not allowed for subscriptions
            return '';
        }

        $item = $order->getLineItems()->first();

        $attributes = new OrderLineItemEntityAttributes($item);

        if (!$attributes->isSubscriptionProduct()) {
            # Mixed carts are not allowed for subscriptions
            return '';
        }

        if ($attributes->getSubscriptionInterval() <= 0) {
            throw new Exception('Invalid subscription interval unit');
        }

        if (empty($attributes->getSubscriptionIntervalUnit())) {
            throw new Exception('Invalid subscription interval unit');
        }

        # extract and build our subscription item from the current order entity.
        $subscription = $this->subscriptionBuilder->buildSubscription($order);

        $this->repoSubscriptions->insertSubscription($subscription, $context->getContext());

        return $subscription->getId();
    }

    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $context
     * @throws \Exception
     */
    public function confirmSubscription(OrderEntity $order, SalesChannelContext $context): void
    {
        if (!$order->getOrderCustomer() instanceof OrderCustomerEntity) {
            throw new \Exception('Order: ' . $order->getOrderNumber() . ' does not have a linked customer');
        }

        $orderSalesChannelId = $order->getSalesChannelId();

        # first get our mollie customer ID from the order.
        # this is required to create a subscription
        $mollieCustomerId = $this->customerService->getMollieCustomerId($order->getOrderCustomer()->getCustomerId(), $orderSalesChannelId, $context->getContext());


        # switch out client to the correct sales channel
        $this->gwMollie->switchClient($orderSalesChannelId);


        # load all pending subscriptions of the order.
        # we will now make sure to create Mollie subscriptions and
        # prepare everything for recurring payments.
        $pendingSubscriptions = $this->repoSubscriptions->findPendingSubscriptions($order->getId(), $context->getContext());

        foreach ($pendingSubscriptions as $subscription) {

            # convert our subscription into a mollie definition
            $mollieData = $this->mollieRequestBuilder->buildDefinition($subscription);
            # create the subscription in Mollie.
            # this is important to really start the subscription process
            $mollieSubscription = $this->gwMollie->createSubscription($mollieCustomerId, $mollieData);

            # confirm the subscription in our local database
            # by adding the missing external Mollie IDs
            $this->repoSubscriptions->confirmSubscription(
                $subscription->getId(),
                $mollieSubscription->id,
                $mollieSubscription->customerId,
                $mollieSubscription->nextPaymentDate,
                $context->getContext()
            );
        }
    }

    /**
     * @param Context $context
     * @return void
     * @throws \Exception
     */
    public function remindSubscriptionRenewal(Context $context): void
    {
        # TODO this is not yet done. in theory we have a different setting in other sales channels..which would mean we would need to iterate channels here!!!
        $settings = $this->pluginSettings->getSettings();

        if (!$settings->isSubscriptionsReminderMailEnabled()) {
            return;
        }

        $today = new DateTime();
        $daysOffset = $settings->getSubscriptionsReminderMailDays();

        $availableSubscriptions = $this->repoSubscriptions->findByReminderRangeReached($daysOffset, $context);

        /** @var SubscriptionEntity $subscription */
        foreach ($availableSubscriptions->getElements() as $subscription) {

            # now check if we are allowed to remind or if it was already done
            $shouldRemind = $this->reminderValidator->shouldRemind(
                $subscription->getNextPaymentAt(),
                $today,
                $daysOffset,
                $subscription->getLastRemindedAt()
            );

            if (!$shouldRemind) {
                continue;
            }

            $customer = $this->customerService->getCustomer($subscription->getCustomerId(), $context);

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $subscription->getSalesChannelId()));
            $salesChannel = $this->repoSalesChannel->search($criteria, $context)->first();

            # --------------------------------------------------------------------------------------------------
            # FLOW BUILDER / BUSINESS EVENTS

            $event = $this->flowBuilderEventFactory->buildSubscriptionRemindedEvent($customer, $subscription, $salesChannel, $context);
            $this->flowBuilderDispatcher->dispatch($event);

            # --------------------------------------------------------------------------------------------------

            $this->repoSubscriptions->markReminded($subscription->getId(), $context);
        }
    }

    /**
     * @param string $swSubscriptionId
     * @param string $molliePaymentId
     * @param SalesChannelContext $context
     * @return OrderEntity
     * @throws \Exception
     */
    public function renewSubscription(string $swSubscriptionId, string $molliePaymentId, SalesChannelContext $context): OrderEntity
    {
        $subscription = $this->repoSubscriptions->findById($swSubscriptionId, $context->getContext());

        $this->gwMollie->switchClient($subscription->getSalesChannelId());
        $payment = $this->gwMollie->getPayment($molliePaymentId);


        $devMode = $this->pluginSettings->getEnvMollieDevMode();

        # if this transaction id is somehow NOT from our subscription
        # then do not proceed and throw an error.
        # in DEV mode, we allow this, otherwise we cannot test this!
        if (!$devMode && (string)$payment->subscriptionId !== $swSubscriptionId) {
            throw new \Exception('Warning, trying to renew subscription based on a payment that does not belong to this subscription!');
        }

        return $this->renewingService->renewSubscription($subscription, $payment, $context);
    }

    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $context
     */
    public function cancelPendingSubscriptions(OrderEntity $order, SalesChannelContext $context): void
    {
        # TODO
    }

    /**
     * @param string $subscriptionId
     * @param Context $context
     */
    public function cancelSubscription(string $subscriptionId, Context $context): void
    {
        $subscription = $this->repoSubscriptions->findById($subscriptionId, $context);

        $this->gwMollie->switchClient($subscription->getSalesChannelId());

        $this->gwMollie->cancelSubscription(
            $subscription->getMollieId(),
            $subscription->getMollieCustomerId()
        );

        $this->repoSubscriptions->cancelSubscription($subscriptionId, $context);
    }

}
