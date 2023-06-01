<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Components\Subscription\SubscriptionManagerInterface;
use Kiener\MolliePayments\Exception\CouldNotCreateMollieCustomerException;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Kiener\MolliePayments\Exception\MollieOrderCancelledException;
use Kiener\MolliePayments\Exception\MollieOrderExpiredException;
use Kiener\MolliePayments\Exception\PaymentUrlException;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderBuilder;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\Order\UpdateOrderLineItems;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\UpdateOrderCustomFields;
use Kiener\MolliePayments\Struct\MolliePaymentPrepareData;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order as MollieOrder;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Environment;
use Twig\Extension\CoreExtension;

class MolliePaymentDoPay
{
    /**
     * @var OrderDataExtractor
     */
    private $extractor;

    /**
     * @var MollieOrderBuilder
     */
    private $orderBuilder;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var Order
     */
    private $mollieGateway;

    /**
     * @var CustomerService
     */
    private $customerService;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var UpdateOrderCustomFields
     */
    private $updaterOrderCustomFields;
    /**
     * @var UpdateOrderLineItems
     */
    private $updaterLineItemCustomFields;

    /**
     * @var SubscriptionManagerInterface
     */
    private $subscriptionManager;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param OrderDataExtractor $extractor
     * @param MollieOrderBuilder $orderBuilder
     * @param OrderService $orderService
     * @param Order $orderApiService
     * @param CustomerService $customerService
     * @param SettingsService $settingsService
     * @param UpdateOrderCustomFields $updateOrderCustomFields
     * @param UpdateOrderLineItems $updateOrderLineItems
     * @param SubscriptionManagerInterface $subscriptionManager
     * @param Environment $twig
     * @param LoggerInterface $logger
     */
    public function __construct(OrderDataExtractor $extractor, MollieOrderBuilder $orderBuilder, OrderService $orderService, Order $orderApiService, CustomerService $customerService, SettingsService $settingsService, UpdateOrderCustomFields $updateOrderCustomFields, UpdateOrderLineItems $updateOrderLineItems, SubscriptionManagerInterface $subscriptionManager, Environment $twig, LoggerInterface $logger)
    {
        $this->extractor = $extractor;
        $this->orderBuilder = $orderBuilder;
        $this->orderService = $orderService;
        $this->mollieGateway = $orderApiService;
        $this->customerService = $customerService;
        $this->settingsService = $settingsService;
        $this->updaterOrderCustomFields = $updateOrderCustomFields;
        $this->updaterLineItemCustomFields = $updateOrderLineItems;
        $this->subscriptionManager = $subscriptionManager;
        $this->twig = $twig;
        $this->logger = $logger;
    }

    /**
     * function starts the payment process at mollie
     *
     * if a mollieOrder has been created before (e.g failed or cancelled result), it will be cancelled first. We do not want any payments
     * through this mollieOrder
     * we prepare an order at mollie
     * we fetch the new order and if we have to lead the customer to mollie payment site we return this url
     * if we do not get a payment url from mollie (may happen if credit card components are active, payment is successful in this cases), we
     * lead customer to transaction finalize url
     *
     * @param string $paymentMethod
     * @param AsyncPaymentTransactionStruct $transactionStruct
     * @param SalesChannelContext $salesChannelContext
     * @param PaymentHandler $paymentHandler
     * @throws ApiException
     * @return MolliePaymentPrepareData
     */
    public function startMolliePayment(string $paymentMethod, AsyncPaymentTransactionStruct $transactionStruct, SalesChannelContext $salesChannelContext, PaymentHandler $paymentHandler): MolliePaymentPrepareData
    {
        # this is the current order transaction
        # of this payment attempt in Shopware
        $swOrderTransactionID = $transactionStruct->getOrderTransaction()->getId();


        // get order with all needed associations
        $order = $this->orderService->getOrder($transactionStruct->getOrder()->getId(), $salesChannelContext->getContext());

        if (!$order instanceof OrderEntity) {
            throw new \Exception('Order in Shopware not existing when preparing Mollie payment');
        }

        # build our custom fields
        # object for this order
        $orderCustomFields = new OrderAttributes($order);
        $orderCustomFields->setTransactionReturnUrl($transactionStruct->getReturnUrl());

        # Store current timezone in the order so we can fix the timezone during webhook calls.
        if ($this->twig->hasExtension(CoreExtension::class)) {
            /** @var CoreExtension $coreExtension */
            $coreExtension = $this->twig->getExtension(CoreExtension::class);
            $orderCustomFields->setTimezone($coreExtension->getTimezone()->getName());
        }

        # extract the main Mollie Order ID "ord_xyz" that
        # we are working on in this case. this is empty for first orders
        # but filled if we do another payment attempt for an existing order.
        $mollieOrderId = $orderCustomFields->getMollieOrderId();


        # now let's check if we have another payment attempt for an existing order.
        # this is the case, if we already have a Mollie Order ID in our custom fields.
        # in this case, we just add a new payment (transaction) to the existing order in Mollie.
        if (!empty($mollieOrderId)) {
            try {
                return $this->handleNextPaymentAttempt(
                    $order,
                    $swOrderTransactionID,
                    $orderCustomFields,
                    $mollieOrderId,
                    $paymentMethod,
                    $transactionStruct,
                    $salesChannelContext,
                    $paymentHandler
                );
            } catch (MollieOrderCancelledException|MollieOrderExpiredException $e) {
                # Warn about cancelled/expired order, but otherwise do nothing and let it create a new order.
                $this->logger->warning($e->getMessage(), [
                    'orderNumber' => $order->getOrderNumber(),
                    'mollieOrderId' => $mollieOrderId,
                ]);
            }
        }

        $this->logger->debug(
            'Start first payment attempt for order: ' . $order->getOrderNumber(),
            [
                'salesChannel' => $salesChannelContext->getSalesChannel()->getName(),
                'mollieID' => '-',
                'shopwareTransactionID' => $swOrderTransactionID,
            ]
        );

        # now create our Mollie customer if we have configured
        # our plugin to do this. This is a fail-safe approach.
        # We just try to create the customer before we create the actual order.
        $this->createCustomerAtMollie($order, $salesChannelContext);

        # let's create our real Mollie order
        # for this payment in Shopware.
        $mollieOrder = $this->createMollieOrder($order, $paymentMethod, $transactionStruct, $salesChannelContext, $paymentHandler);

        # now create subscriptions from our order for
        # all products that are configured to be a subscription.
        # this will prepare the subscriptions in our database.
        # the confirmation of these, however, will be done in a webhook
        $subscriptionId = $this->subscriptionManager->createSubscription($order, $salesChannelContext);

        # now update our custom struct values
        # and immediately set our Mollie Order ID and more
        $orderCustomFields->setMollieOrderId($mollieOrder->id);
        $orderCustomFields->setMolliePaymentUrl($mollieOrder->getCheckoutUrl());

        # if we have a subscription, make sure
        # to remember the ID in our order
        if (!empty($subscriptionId)) {
            $orderCustomFields->setSubscriptionData($subscriptionId, '');
        }

        # we save that data in both, the order and
        # the order line items
        $this->updaterOrderCustomFields->updateOrder($order->getId(), $orderCustomFields, $salesChannelContext->getContext());
        $this->updaterLineItemCustomFields->updateOrderLineItems($mollieOrder, $salesChannelContext);


        # this condition somehow looks weird to me (TODO)
        $checkoutURL = $orderCustomFields->getMolliePaymentUrl() ?? $orderCustomFields->getTransactionReturnUrl() ?? $transactionStruct->getReturnUrl();


        return new MolliePaymentPrepareData((string)$checkoutURL, (string)$mollieOrder->id);
    }

    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $salesChannelContext
     * @return void
     */
    public function createCustomerAtMollie(OrderEntity $order, SalesChannelContext $salesChannelContext): void
    {
        try {
            $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannel()->getId());

            $orderAttributes = new OrderAttributes($order);
            $isSubscription = $orderAttributes->isTypeSubscription();

            $customer = $this->extractor->extractCustomer($order, $salesChannelContext);

            $oneClickShouldSaveCard = false;
            $oneClickIsReused = false;

            $customFields = $customer->getCustomFields();

            if (isset($customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS])) {
                $mollieData = $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS];

                $oneClickShouldSaveCard = (isset($mollieData[CustomerService::CUSTOM_FIELDS_KEY_SHOULD_SAVE_CARD_DETAIL])) ? (bool)$mollieData[CustomerService::CUSTOM_FIELDS_KEY_SHOULD_SAVE_CARD_DETAIL] : false;
                $oneClickIsReused = (isset($mollieData[CustomerService::CUSTOM_FIELDS_KEY_MANDATE_ID])) ? (bool)$mollieData[CustomerService::CUSTOM_FIELDS_KEY_MANDATE_ID] : false;
            }

            # create customers for every subscription
            # or if we don't have a guest and our feature is enabled
            $createCustomer = false;

            # subscription requires it
            if ($isSubscription) {
                $createCustomer = true;
            }

            # if real customer, and setting is enabled
            if (!$customer->getGuest() && $settings->createCustomersAtMollie()) {
                $createCustomer = true;
            }

            # if we have a customer that wants to save a mandate or reuse it, and its no guest and our setting is also enabled
            # then create a customer on the mollie side
            if (($oneClickShouldSaveCard || $oneClickIsReused) && !$customer->getGuest() && $settings->isOneClickPaymentsEnabled()) {
                $createCustomer = true;
            }

            if ($createCustomer) {
                $this->customerService->createMollieCustomer(
                    $customer->getId(),
                    $salesChannelContext->getSalesChannel()->getId(),
                    $salesChannelContext->getContext()
                );
            }
        } catch (CouldNotCreateMollieCustomerException|CustomerCouldNotBeFoundException $e) {
            # TODO do we really need to catch this? shouldnt it fail fast?

            $this->logger->error(
                $e->getMessage(),
                [
                    'saleschannel' => $salesChannelContext->getSalesChannel()->getName(),
                    'order' => $order->getOrderNumber(),
                ]
            );
        }
    }

    /**
     * @param OrderEntity $order
     * @param string $swOrderTransactionID
     * @param OrderAttributes $orderCustomFields
     * @param string $mollieOrderId
     * @param string $paymentMethod
     * @param AsyncPaymentTransactionStruct $transactionStruct
     * @param SalesChannelContext $salesChannelContext
     * @param PaymentHandler $paymentHandler
     * @throws ApiException|MollieOrderCancelledException|MollieOrderExpiredException
     * @return MolliePaymentPrepareData
     */
    private function handleNextPaymentAttempt(OrderEntity $order, string $swOrderTransactionID, OrderAttributes $orderCustomFields, string $mollieOrderId, string $paymentMethod, AsyncPaymentTransactionStruct $transactionStruct, SalesChannelContext $salesChannelContext, PaymentHandler $paymentHandler): MolliePaymentPrepareData
    {
        $this->logger->debug(
            'Start additional payment attempt for order: ' . $order->getOrderNumber(),
            [
                'salesChannel' => $salesChannelContext->getSalesChannel()->getName(),
                'mollieID' => $mollieOrderId,
                'shopwareTransactionID' => $swOrderTransactionID,
            ]
        );

        $customer = $this->extractor->extractCustomer($order, $salesChannelContext);


        # now create a new payment attempt for the order
        # we should get a Mollie payment object as a result.
        $payment = $this->mollieGateway->createOrReusePayment(
            $mollieOrderId,
            $paymentMethod,
            $swOrderTransactionID,
            $paymentHandler,
            $order,
            $customer,
            $salesChannelContext
        );


        # somehow it can be that the status is already APPROVED and our checkoutURL is thus empty.
        # This usually happens for Apple Pay and non-3d-secure CCs, but I don't know why it should happen in a second payment attempt?.
        # anyway, in that case we need to immediately return to the finish page
        if (MolliePaymentStatus::isApprovedStatus($payment->status) && empty($payment->getCheckoutUrl())) {
            return new MolliePaymentPrepareData($transactionStruct->getReturnUrl(), $mollieOrderId);
        }

        if (empty($payment->getCheckoutUrl())) {
            throw new PaymentUrlException($transactionStruct->getOrderTransaction()->getId(), "Couldn't get Mollie payment CheckoutURL for " . $payment->id);
        }

        // save custom fields because shopware return url could have changed
        // e.g. if changedPayment Parameter has to be added the shopware payment token changes
        $orderCustomFields->setMolliePaymentUrl($payment->getCheckoutUrl());
        $this->updaterOrderCustomFields->updateOrder($order->getId(), $orderCustomFields, $salesChannelContext->getContext());

        return new MolliePaymentPrepareData($payment->getCheckoutUrl(), $mollieOrderId);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param string $paymentMethod
     * @param AsyncPaymentTransactionStruct $transactionStruct
     * @param SalesChannelContext $salesChannelContext
     * @param PaymentHandler $paymentHandler
     * @throws \Exception
     * @return MollieOrder
     */
    private function createMollieOrder(OrderEntity $orderEntity, string $paymentMethod, AsyncPaymentTransactionStruct $transactionStruct, SalesChannelContext $salesChannelContext, PaymentHandler $paymentHandler): \Mollie\Api\Resources\Order
    {
        $salesChannelID = $orderEntity->getSalesChannelId();

        $params = $this->orderBuilder->build(
            $orderEntity,
            $transactionStruct->getOrderTransaction()->getId(),
            $paymentMethod,
            $salesChannelContext,
            $paymentHandler
        );

        return $this->mollieGateway->createOrder(
            $params,
            $salesChannelID,
            $salesChannelContext
        );
    }
}
