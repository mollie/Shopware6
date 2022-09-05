<?php

namespace Kiener\MolliePayments\Components\Subscription;

use DateTime;
use Exception;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactoryInterface;
use Kiener\MolliePayments\Components\Subscription\DAL\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\MollieStatus;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\Exception\SubscriptionSkippedException;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\MollieDataBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\SubscriptionBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\PaymentMethodRemover\SubscriptionRemover;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionCancellation\CancellationValidator;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionReminder\ReminderValidator;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionRenewing\SubscriptionRenewing;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Router\RoutingBuilder;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

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
     * @var OrderService
     */
    private $orderService;

    /**
     * @var MollieGatewayInterface
     */
    private $gwMollie;

    /**
     * @var ReminderValidator
     */
    private $reminderValidator;

    /**
     * @var CancellationValidator
     */
    private $cancellationValidator;

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
     * @var RoutingBuilder
     */
    private $routingBuilder;

    /**
     * @var MollieOrderPriceBuilder
     */
    private $priceBuilder;

    /**
     * @var OrderStatusConverter
     */
    private $statusConverter;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param FlowBuilderFactoryInterface $flowBuilderFactory
     * @param FlowBuilderEventFactory $flowBuilderEventFactory
     * @param SubscriptionRepository $repoSubscriptions
     * @param OrderService $orderService
     * @param SettingsService $settingsService
     * @param MollieDataBuilder $definitionBuilder
     * @param SubscriptionBuilder $subscriptionBuilder
     * @param CustomerService $customers
     * @param MollieGatewayInterface $gatewayMollie
     * @param SubscriptionRenewing $renewingService
     * @param EntityRepositoryInterface $repoSalesChannel
     * @param MollieOrderPriceBuilder $priceBuilder
     * @param OrderStatusConverter $statusConverter
     * @param LoggerInterface $logger
     */
    public function __construct(FlowBuilderFactoryInterface $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory, SubscriptionRepository $repoSubscriptions, OrderService $orderService, SettingsService $settingsService, MollieDataBuilder $definitionBuilder, SubscriptionBuilder $subscriptionBuilder, CustomerService $customers, MollieGatewayInterface $gatewayMollie, SubscriptionRenewing $renewingService, EntityRepositoryInterface $repoSalesChannel, RoutingBuilder $routingBuilder, MollieOrderPriceBuilder $priceBuilder, OrderStatusConverter $statusConverter, LoggerInterface $logger)
    {
        $this->pluginSettings = $settingsService;
        $this->repoSubscriptions = $repoSubscriptions;
        $this->mollieRequestBuilder = $definitionBuilder;
        $this->subscriptionBuilder = $subscriptionBuilder;
        $this->customerService = $customers;
        $this->gwMollie = $gatewayMollie;
        $this->renewingService = $renewingService;
        $this->repoSalesChannel = $repoSalesChannel;
        $this->orderService = $orderService;
        $this->routingBuilder = $routingBuilder;
        $this->priceBuilder = $priceBuilder;
        $this->statusConverter = $statusConverter;
        $this->logger = $logger;

        $this->flowBuilderEventFactory = $flowBuilderEventFactory;
        $this->flowBuilderDispatcher = $flowBuilderFactory->createDispatcher();

        $this->reminderValidator = new ReminderValidator();
        $this->cancellationValidator = new CancellationValidator();
    }

    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $context
     * @throws Exception
     * @return string
     */
    public function createSubscription(OrderEntity $order, SalesChannelContext $context): string
    {
        $settings = $this->pluginSettings->getSettings($context->getSalesChannelId());

        if (!$settings->isSubscriptionsEnabled()) {
            return '';
        }

        if ($order->getLineItems() === null || $order->getLineItems()->count() > 1) {
            # Mixed carts are not allowed for subscriptions
            return '';
        }

        $item = $order->getLineItems()->first();

        if (!$item instanceof OrderLineItemEntity) {
            throw new Exception('No line item entity found for order ' . $order->getOrderNumber());
        }


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

        $this->logger->debug('Creating Subscription entry for order: ' . $order->getOrderNumber());

        # extract and build our subscription item from the current order entity.
        $subscription = $this->subscriptionBuilder->buildSubscription($order);

        $this->repoSubscriptions->insertSubscription($subscription, $context->getContext());

        return $subscription->getId();
    }

    /**
     * @param OrderEntity $order
     * @param string $mandateId
     * @param Context $context
     * @throws CustomerCouldNotBeFoundException
     * @return void
     */
    public function confirmSubscription(OrderEntity $order, string $mandateId, Context $context): void
    {
        if (!$order->getOrderCustomer() instanceof OrderCustomerEntity) {
            throw new \Exception('Order: ' . $order->getOrderNumber() . ' does not have a linked customer');
        }

        $settings = $this->pluginSettings->getSettings($order->getSalesChannelId());

        if (!$settings->isSubscriptionsEnabled()) {
            return;
        }


        # first get our mollie customer ID from the order.
        # this is required to create a subscription
        $mollieCustomerId = $this->customerService->getMollieCustomerId((string)$order->getOrderCustomer()->getCustomerId(), $order->getSalesChannelId(), $context);


        # switch out client to the correct sales channel
        $this->gwMollie->switchClient($order->getSalesChannelId());


        # load all pending subscriptions of the order.
        # we will now make sure to create Mollie subscriptions and
        # prepare everything for recurring payments.
        $pendingSubscriptions = $this->repoSubscriptions->findPendingSubscriptions($order->getId(), $context);

        # if we have nothing to confirm
        # then just return
        if (count($pendingSubscriptions) <= 0) {
            return;
        }

        # if we have something to confirm (and create) but we don't
        # have a valid mandate ID, then throw an exception
        if (empty($mandateId)) {
            throw new \Exception('Cannot confirm subscription for order: ' . $order->getOrderNumber() . '! Provided mandate ID of payment is empty!');
        }

        $this->logger->debug('Confirming pending subscription for order: ' . $order->getOrderNumber());

        foreach ($pendingSubscriptions as $subscription) {

            # convert our subscription into a mollie definition
            $jsonPayload = $this->mollieRequestBuilder->buildRequestPayload($subscription, $mandateId);
            # create the subscription in Mollie.
            # this is important to really start the subscription process
            $mollieSubscription = $this->gwMollie->createSubscription($mollieCustomerId, $jsonPayload);

            # confirm the subscription in our local database
            # by adding the missing external Mollie IDs
            $this->repoSubscriptions->confirmSubscription(
                $subscription->getId(),
                (string)$mollieSubscription->id,
                (string)$mollieSubscription->customerId,
                (string)$mollieSubscription->nextPaymentDate,
                $context
            );

            # FLOW BUILDER / BUSINESS EVENTS
            $event = $this->flowBuilderEventFactory->buildSubscriptionStartedEvent($subscription->getCustomer(), $subscription, $context);
            $this->flowBuilderDispatcher->dispatch($event);
        }
    }

    /**
     * @param Context $context
     * @throws Exception
     * @return int
     */
    public function remindSubscriptionRenewal(Context $context): int
    {
        $remindedCount = 0;

        $today = new DateTime();

        $salesChannels = $this->repoSalesChannel->search(new Criteria(), $context);


        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            $settings = $this->pluginSettings->getSettings($salesChannel->getId());

            if (!$settings->isSubscriptionsEnabled()) {
                continue;
            }

            $daysOffset = $settings->getSubscriptionsReminderDays();

            $availableSubscriptions = $this->repoSubscriptions->findByReminderRangeReached($daysOffset, $salesChannel->getId(), $context);

            /** @var SubscriptionEntity $subscription */
            foreach ($availableSubscriptions->getElements() as $subscription) {

                # if it's not active in Mollie, then don't do anything
                if ($subscription->getMollieStatus() !== MollieStatus::ACTIVE) {
                    continue;
                }

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

                if (!$customer instanceof CustomerEntity) {
                    throw new Exception('Shopware Customer not found for Subscription! Cannot remind anyone!');
                }

                # --------------------------------------------------------------------------------------------------
                # FLOW BUILDER / BUSINESS EVENTS

                $event = $this->flowBuilderEventFactory->buildSubscriptionRemindedEvent($customer, $subscription, $salesChannel, $context);
                $this->flowBuilderDispatcher->dispatch($event);

                # --------------------------------------------------------------------------------------------------

                $this->repoSubscriptions->markReminded($subscription->getId(), $context);

                $remindedCount++;
            }
        }

        return $remindedCount;
    }

    /**
     * @param string $swSubscriptionId
     * @param string $molliePaymentId
     * @param Context $context
     * @throws Exception
     * @return OrderEntity
     */
    public function renewSubscription(string $swSubscriptionId, string $molliePaymentId, Context $context): OrderEntity
    {
        # we need a custom exception here
        # to avoid errors like "Return value of...not instance of SubscriptionEntity
        try {
            $swSubscription = $this->repoSubscriptions->findById($swSubscriptionId, $context);
        } catch (\Throwable $ex) {
            throw new Exception('Subscription with ID ' . $swSubscriptionId . ' not found in Shopware');
        }

        $settings = $this->pluginSettings->getSettings($swSubscription->getSalesChannelId());

        if (!$settings->isSubscriptionsEnabled()) {
            throw new Exception('Subscription with ID ' . $swSubscriptionId . ' not renewed. Subscriptions are disabled for this Sales Channel');
        }

        # only renew active subscriptions
        # however, if it's the last time, then it's unfortunately already "completed"
        # so we also need to allow this.
        if ($swSubscription->getMollieStatus() !== MollieStatus::ACTIVE && $swSubscription->getMollieStatus() !== MollieStatus::COMPLETED) {
            throw new Exception('Subscription is not active and cannot be edited');
        }

        $this->gwMollie->switchClient($swSubscription->getSalesChannelId());

        # grab our mollie payment and also the mollie subscription
        $payment = $this->gwMollie->getPayment($molliePaymentId);
        $mollieSubscription = $this->gwMollie->getSubscription($swSubscription->getMollieId(), $swSubscription->getMollieCustomerId());

        $devMode = $this->pluginSettings->getEnvMollieDevMode();

        # if this transaction id is somehow NOT from our subscription
        # then do not proceed and throw an error.
        # in DEV mode, we allow this, otherwise we cannot test this!
        if (!$devMode && (string)$payment->subscriptionId !== $swSubscription->getMollieId()) {
            throw new \Exception('Warning, trying to renew subscription based on a payment that does not belong to this subscription!');
        }

        # verify if the amount is higher than 0,00
        # we just want to ensure that a "payment method update" does not lead to this webhook (it felt as if it was in 1 case)
        if ((float)$payment->amount->value <= 0) {
            throw new \Exception('Warning, trying to renew subscription based on a 0,00 payment. Mollie should actually not call the renew-webhook for this!');
        }


        $salesChannelSettings = $this->pluginSettings->getSettings($swSubscription->getSalesChannelId());

        # It's possible to automatically skip failed payments and avoid that new orders are created. This is a plugin configuration.
        # If skipping is enabled, and the payment status is not approved, then we throw an error and skip the renewal (for this payment attempt).
        if ($salesChannelSettings->isSubscriptionSkipRenewalsOnFailedPayments()) {
            $status = $this->statusConverter->getMolliePaymentStatus($payment);

            if (!MolliePaymentStatus::isApprovedStatus($status)) {
                # let's throw a specific exception, because we need to
                # handle the response for Mollie with 200 OK
                throw new SubscriptionSkippedException($swSubscriptionId, $payment->id);
            }
        }

        # first thing is, we have to update our new paymentAt of our local subscription.
        # we do this immediately because we get the correct data from Mollie anyway
        $this->repoSubscriptions->updateNextPaymentAt(
            $swSubscriptionId,
            (string)$mollieSubscription->nextPaymentDate,
            $context
        );

        $newOrder = $this->renewingService->renewSubscription($swSubscription, $payment, $context);

        # --------------------------------------------------------------------------------------------------
        # FLOW BUILDER / BUSINESS EVENTS

        $event = $this->flowBuilderEventFactory->buildSubscriptionRenewedEvent($swSubscription->getCustomer(), $swSubscription, $context);
        $this->flowBuilderDispatcher->dispatch($event);

        # if this was our last renewal, then send out
        # a new event that the subscription has now ended
        if ($mollieSubscription->timesRemaining !== null && $mollieSubscription->timesRemaining <= 0) {
            $event = $this->flowBuilderEventFactory->buildSubscriptionEndedEvent($swSubscription->getCustomer(), $swSubscription, $context);
            $this->flowBuilderDispatcher->dispatch($event);
        }

        # --------------------------------------------------------------------------------------------------

        return $newOrder;
    }

    /**
     * @param string $subscriptionId
     * @param string $salutationId
     * @param string $title
     * @param string $firstname
     * @param string $lastname
     * @param string $company
     * @param string $department
     * @param string $additional1
     * @param string $additional2
     * @param string $phoneNumber
     * @param string $street
     * @param string $zipcode
     * @param string $city
     * @param string $countryStateId
     * @param Context $context
     * @throws Exception
     */
    public function updateBillingAddress(string $subscriptionId, string $salutationId, string $title, string $firstname, string $lastname, string $company, string $department, string $additional1, string $additional2, string $phoneNumber, string $street, string $zipcode, string $city, string $countryStateId, Context $context): void
    {
        $subscription = $this->repoSubscriptions->findById($subscriptionId, $context);

        if ($subscription->getMollieStatus() !== MollieStatus::ACTIVE) {
            throw new Exception('Subscription is not active and cannot be edited');
        }

        $settings = $this->pluginSettings->getSettings($subscription->getSalesChannelId());

        if (!$settings->isSubscriptionsEnabled()) {
            throw new Exception('Subscription Billing Address cannot be updated. Subscriptions are disabled for this Sales Channel');
        }

        if (!$settings->isSubscriptionsAllowAddressEditing()) {
            throw new Exception('Editing of the billing address on running subscriptions is not allowed in the plugin configuration');
        }

        $address = $subscription->getBillingAddress();

        if (!$address instanceof SubscriptionAddressEntity) {
            $address = $this->createNewAddress($subscription, $context);
        }

        $address->setSalutationId($salutationId);
        $address->setTitle($title);
        $address->setFirstName($firstname);
        $address->setLastName($lastname);

        $address->setCompany($company);
        $address->setDepartment($department);

        $address->setAdditionalAddressLine1($additional1);
        $address->setAdditionalAddressLine2($additional2);

        $address->setPhoneNumber($phoneNumber);

        $address->setStreet($street);
        $address->setZipcode($zipcode);
        $address->setCity($city);
        $address->setCountryStateId($countryStateId);

        $this->repoSubscriptions->assignBillingAddress($subscriptionId, $address, $context);
    }

    /**
     * @param string $subscriptionId
     * @param string $salutationId
     * @param string $title
     * @param string $firstname
     * @param string $lastname
     * @param string $company
     * @param string $department
     * @param string $additional1
     * @param string $additional2
     * @param string $phoneNumber
     * @param string $street
     * @param string $zipcode
     * @param string $city
     * @param string $countryStateId
     * @param Context $context
     * @throws Exception
     */
    public function updateShippingAddress(string $subscriptionId, string $salutationId, string $title, string $firstname, string $lastname, string $company, string $department, string $additional1, string $additional2, string $phoneNumber, string $street, string $zipcode, string $city, string $countryStateId, Context $context): void
    {
        $subscription = $this->repoSubscriptions->findById($subscriptionId, $context);

        if ($subscription->getMollieStatus() !== MollieStatus::ACTIVE) {
            throw new Exception('Subscription is not active and cannot be edited');
        }

        $settings = $this->pluginSettings->getSettings($subscription->getSalesChannelId());

        if (!$settings->isSubscriptionsEnabled()) {
            throw new Exception('Subscription Shipping Address cannot be updated. Subscriptions are disabled for this Sales Channel');
        }

        if (!$settings->isSubscriptionsAllowAddressEditing()) {
            throw new Exception('Editing of the shipping address on running subscriptions is not allowed in the plugin configuration');
        }

        $address = $subscription->getShippingAddress();

        if (!$address instanceof SubscriptionAddressEntity) {
            $address = $this->createNewAddress($subscription, $context);
        }

        $address->setSalutationId($salutationId);
        $address->setTitle($title);
        $address->setFirstName($firstname);
        $address->setLastName($lastname);

        $address->setCompany($company);
        $address->setDepartment($department);

        $address->setPhoneNumber($phoneNumber);

        $address->setAdditionalAddressLine1($additional1);
        $address->setAdditionalAddressLine2($additional2);

        $address->setStreet($street);
        $address->setZipcode($zipcode);
        $address->setCity($city);
        $address->setCountryStateId($countryStateId);

        $this->repoSubscriptions->assignShippingAddress($subscriptionId, $address, $context);
    }

    /**
     * @param string $subscriptionId
     * @param Context $context
     * @throws CustomerCouldNotBeFoundException
     * @return string
     */
    public function updatePaymentMethodStart(string $subscriptionId, Context $context): string
    {
        $subscription = $this->repoSubscriptions->findById($subscriptionId, $context);

        if ($subscription->getMollieStatus() !== MollieStatus::ACTIVE) {
            throw new Exception('Subscription is not active and cannot be edited');
        }

        $settings = $this->pluginSettings->getSettings($subscription->getSalesChannelId());

        if (!$settings->isSubscriptionsEnabled()) {
            throw new Exception('Subscription Payment Method cannot be updated. Subscriptions are disabled for this Sales Channel');
        }

        # first load our customer ID
        # every subscription customer should have already a Mollie customer ID
        $customerStruct = $this->customerService->getCustomerStruct($subscription->getCustomerId(), $context);
        $customerId = $customerStruct->getCustomerId((string)$settings->getProfileId(), $settings->isTestMode());

        # now create our payment.
        # it's important to use a sequenceType first to allow 0,00 amount payment.
        # this will be used to process the payment and get/create a new mandate inside the Mollie API systems.
        $payment = $this->gwMollie->createPayment([
            'sequenceType' => 'first',
            'customerId' => $customerId,
            'method' => SubscriptionRemover::ALLOWED_METHODS,
            'amount' => $this->priceBuilder->build(0, 'EUR'),
            'description' => 'Update Subscription Payment: ' . $subscription->getDescription(),
            'redirectUrl' => $this->routingBuilder->buildSubscriptionPaymentUpdated($subscriptionId),
        ]);

        # now update our metadata and set the temporary transaction ID.
        # we need this in the redirectURL to verify if this
        # payment was successful or if it failed.
        $meta = $subscription->getMetadata();
        $meta->setTmpTransaction($payment->id);
        $this->repoSubscriptions->updateSubscriptionMetadata($subscription->getId(), $meta, $context);

        # simply return the checkoutURL to redirect the customer
        return (string)$payment->getCheckoutUrl();
    }

    /**
     * @param string $subscriptionId
     * @param Context $context
     * @throws Exception
     * @return void
     */
    public function updatePaymentMethodConfirm(string $subscriptionId, Context $context): void
    {
        $subscription = $this->repoSubscriptions->findById($subscriptionId, $context);

        if ($subscription->getMollieStatus() !== MollieStatus::ACTIVE) {
            throw new Exception('Subscription is not active and cannot be edited');
        }

        # load our latest tmp_transaction ID that was used
        # to initialize the payment of the update.
        # we have to verify if it was indeed successful
        $latestTransactionId = $subscription->getMetadata()->getTmpTransaction();

        if (empty($latestTransactionId)) {
            throw new Exception('No temporary transaction existing for this subscription');
        }

        # load our Mollie Payment with this
        # temporary transaction ID
        $this->gwMollie->switchClient($subscription->getSalesChannelId());
        $payment = $this->gwMollie->getPayment($latestTransactionId);

        # now verify if the payment was indeed
        # successful and that our subscription mandate can be updated
        # based on the mandateId in this payment
        $status = $this->statusConverter->getMolliePaymentStatus($payment);
        if (!MolliePaymentStatus::isApprovedStatus($status)) {
            throw new Exception('Payment failed when updating subscription payment method');
        }

        # now update our Mollie subscription
        # with the new mandateId of the approved payment
        $this->gwMollie->updateSubscription(
            $subscription->getMollieId(),
            $subscription->getMollieCustomerId(),
            (string)$payment->mandateId
        );

        # after updating our mandate ID,
        # make sure to remove our temporary transaction ID again
        $meta = $subscription->getMetadata();
        $meta->setTmpTransaction('');
        $this->repoSubscriptions->updateSubscriptionMetadata($subscription->getId(), $meta, $context);
    }

    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return void
     */
    public function cancelPendingSubscriptions(OrderEntity $order, Context $context): void
    {
        # does nothing for now, not necessary
        # because it is not even confirmed yet.
        # but maybe we should add an even in here....
        # let's keep this for now to have it (speaking of the wrapper) fully implemented...
    }

    /**
     * @param string $subscriptionId
     * @param Context $context
     * @throws Exception
     * @return void
     */
    public function cancelSubscription(string $subscriptionId, Context $context): void
    {
        $subscription = $this->repoSubscriptions->findById($subscriptionId, $context);

        $settings = $this->pluginSettings->getSettings($subscription->getSalesChannelId());

        $cancellationDays = $settings->getSubscriptionsCancellationDays();

        # now verify if we are in a valid range to cancel the subscription
        # depending on the plugin configuration it might only be possible
        # up until a few days before the renewal
        $allowCancellation = $this->cancellationValidator->isCancellationAllowed(
            $subscription->getNextPaymentAt(),
            $cancellationDays,
            new DateTime()
        );

        if (!$allowCancellation) {
            throw new Exception('Cancellation of the subscription is not possible anymore. You can only cancel subscriptions ' . $cancellationDays . ' days before the renewal!');
        }


        $this->gwMollie->switchClient($subscription->getSalesChannelId());

        $this->gwMollie->cancelSubscription(
            $subscription->getMollieId(),
            $subscription->getMollieCustomerId()
        );

        $this->repoSubscriptions->cancelSubscription($subscriptionId, $context);

        # FLOW BUILDER / BUSINESS EVENTS
        $event = $this->flowBuilderEventFactory->buildSubscriptionCancelledEvent($subscription->getCustomer(), $subscription, $context);
        $this->flowBuilderDispatcher->dispatch($event);
    }


    /**
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @throws Exception
     * @return SubscriptionAddressEntity
     */
    private function createNewAddress(SubscriptionEntity $subscription, Context $context): SubscriptionAddressEntity
    {
        $initialOrder = $this->orderService->getOrder($subscription->getOrderId(), $context);

        if (!$initialOrder instanceof OrderEntity) {
            throw new Exception('No initial order found for subscription: ' . $subscription->getId());
        }

        $initialAddress = $initialOrder->getBillingAddress();

        if (!$initialAddress instanceof OrderAddressEntity) {
            throw new Exception('No address found for initial order');
        }

        $address = new SubscriptionAddressEntity();

        $address->setId(Uuid::randomHex());
        $address->setCountryId($initialAddress->getCountryId());

        return $address;
    }
}
