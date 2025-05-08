<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Components\Subscription\SubscriptionManagerInterface;
use Kiener\MolliePayments\Exception\CouldNotCreateMollieCustomerException;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Kiener\MolliePayments\Exception\MollieOrderCancelledException;
use Kiener\MolliePayments\Exception\MollieOrderExpiredException;
use Kiener\MolliePayments\Exception\PaymentUrlException;
use Kiener\MolliePayments\Handler\Method\CreditCardPayment;
use Kiener\MolliePayments\Handler\Method\PayPalExpressPayment;
use Kiener\MolliePayments\Handler\Method\PosPayment;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderBuilder;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\Order\UpdateOrderLineItems;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\UpdateOrderCustomFields;
use Kiener\MolliePayments\Struct\MolliePaymentPrepareData;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Kiener\MolliePayments\Traits\StringTrait;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\OrderLine;
use Mollie\Shopware\Component\Transaction\PaymentTransactionStruct;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Environment;
use Twig\Extension\CoreExtension;

class MolliePaymentDoPay
{
    use StringTrait;

    /**
     * @var OrderDataExtractor
     */
    private $extractor;

    /**
     * @var MollieOrderBuilder
     */
    private $orderBuilder;

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

    public function __construct(OrderDataExtractor $extractor, MollieOrderBuilder $orderBuilder, Order $orderApiService, CustomerService $customerService, SettingsService $settingsService, UpdateOrderCustomFields $updateOrderCustomFields, UpdateOrderLineItems $updateOrderLineItems, SubscriptionManagerInterface $subscriptionManager, Environment $twig, LoggerInterface $logger)
    {
        $this->extractor = $extractor;
        $this->orderBuilder = $orderBuilder;

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
     * @throws ApiException
     */
    public function startMolliePayment(string $paymentMethod, PaymentTransactionStruct $transactionStruct, SalesChannelContext $salesChannelContext, PaymentHandler $paymentHandler, RequestDataBag $dataBag): MolliePaymentPrepareData
    {
        $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannelId());

        // this is the current order transaction
        // of this payment attempt in Shopware
        $swOrderTransactionID = $transactionStruct->getOrderTransactionId();

        // get order with all needed associations

        $order = $transactionStruct->getOrder();

        // build our custom fields
        // object for this order
        $orderCustomFields = new OrderAttributes($order);
        $orderCustomFields->setTransactionReturnUrl($transactionStruct->getReturnUrl());
        $orderCustomFields->setMolliePaymentMethod($paymentMethod);
        // Store current timezone in the order so we can fix the timezone during webhook calls.
        if ($this->twig->hasExtension(CoreExtension::class)) {
            /** @var CoreExtension $coreExtension */
            $coreExtension = $this->twig->getExtension(CoreExtension::class);
            $orderCustomFields->setTimezone($coreExtension->getTimezone()->getName());
        }

        // extract the main Mollie Order ID "ord_xyz" that
        // we are working on in this case. this is empty for first orders
        // but filled if we do another payment attempt for an existing order.
        $mollieOrderId = $orderCustomFields->getMollieOrderId();

        $bancomatPayPhoneNumber = $dataBag->get('mollieBancomatPayPhone');

        if ($bancomatPayPhoneNumber !== null) {
            // # we need to pass the custom fields now, so we can use them in create order and display the number on failed orders
            $orderCustomFields->setBancomatPayPhoneNumber($bancomatPayPhoneNumber);
            $order->setCustomFields($orderCustomFields->toArray());
            $this->updaterOrderCustomFields->updateOrder($order->getId(), $orderCustomFields, $salesChannelContext->getContext());
        }

        // now let's check if we have another payment attempt for an existing order.
        // this is the case, if we already have a Mollie Order ID in our custom fields.
        // in this case, we just add a new payment (transaction) to the existing order in Mollie.
        // DO NEVER reuse a POS payment, because that only works with payments and not with orders!!!
        if (! $paymentHandler instanceof PosPayment && $this->stringStartsWith($mollieOrderId, 'ord_')) {
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
                // Warn about cancelled/expired order, but otherwise do nothing and let it create a new order.
                $this->logger->warning($e->getMessage(), [
                    'orderNumber' => $order->getOrderNumber(),
                    'mollieOrderId' => $mollieOrderId,
                ]);
            }
        }

        $this->logger->debug(
            'Start payment attempt for order: ' . $order->getOrderNumber(),
            [
                'salesChannel' => $salesChannelContext->getSalesChannel()->getName(),
                'mollieID' => '-',
                'shopwareTransactionID' => $swOrderTransactionID,
            ]
        );

        // now create our Mollie customer if we have configured
        // our plugin to do this. This is a fail-safe approach.
        // We just try to create the customer before we create the actual order.
        $this->createCustomerAtMollie($order, $salesChannelContext);

        // let's create our real Mollie order
        // for this payment in Shopware.
        $molliePaymentData = $this->createMollieOrder($order, $paymentMethod, $transactionStruct, $salesChannelContext, $paymentHandler);

        // now create subscriptions from our order for
        // all products that are configured to be a subscription.
        // this will prepare the subscriptions in our database.
        // the confirmation of these, however, will be done in a webhook
        $subscriptionId = $this->subscriptionManager->createSubscription($order, $salesChannelContext);

        // now update our custom struct values
        // and immediately set our Mollie Order ID and more
        $orderCustomFields->setMollieOrderId($molliePaymentData->getId());
        $orderCustomFields->setMolliePaymentUrl($molliePaymentData->getCheckoutUrl());

        // if we have a subscription, make sure
        // to remember the ID in our order
        if (! empty($subscriptionId)) {
            $orderCustomFields->setSubscriptionData($subscriptionId, '');
        }

        /**
         * @var OrderLineItemCollection $orderLineItems
         */
        $orderLineItems = $order->getLineItems();
        // we save that data in both, the order and
        // the order line items
        $this->updaterOrderCustomFields->updateOrder($order->getId(), $orderCustomFields, $salesChannelContext->getContext());
        $this->updaterLineItemCustomFields->updateOrderLineItems($molliePaymentData->getMollieLineItems(), $orderLineItems, $salesChannelContext);

        // this condition somehow looks weird to me (TODO)
        $checkoutURL = $orderCustomFields->getMolliePaymentUrl() ?? $orderCustomFields->getTransactionReturnUrl() ?? $transactionStruct->getReturnUrl();

        if (empty($checkoutURL)) {
            // see if we have a POS payment
            if ($paymentHandler instanceof PosPayment) {
                // TODO use route builder?! but its only a temp solution...mollie will build a page anyway
                $checkoutURL = '/mollie/pos/checkout?sw=' . $order->getId() . '&mo=' . $molliePaymentData->getId();

                // if we are in test mode then
                // also include the status change url
                if ($settings->isTestMode() && ! empty($molliePaymentData->getChangeStatusUrl())) {
                    $checkoutURL .= '&cs=' . urlencode($molliePaymentData->getChangeStatusUrl());
                }
            }

            // if we save credit card information, we do not get a checkout url, so we have to use transactionStruct
            if ($paymentHandler instanceof CreditCardPayment) {
                $checkoutURL = $transactionStruct->getReturnUrl();
            }

            // paypal express does not have a redirect since we were already on paypal site before
            if ($paymentHandler instanceof PayPalExpressPayment) {
                $checkoutURL = $transactionStruct->getReturnUrl();
            }
        }

        return new MolliePaymentPrepareData((string) $checkoutURL, (string) $molliePaymentData->getId());
    }

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

            if (isset($customFields[CustomFieldsInterface::MOLLIE_KEY])) {
                $mollieData = $customFields[CustomFieldsInterface::MOLLIE_KEY];

                $oneClickShouldSaveCard = (isset($mollieData[CustomerService::CUSTOM_FIELDS_KEY_SHOULD_SAVE_CARD_DETAIL])) ? (bool) $mollieData[CustomerService::CUSTOM_FIELDS_KEY_SHOULD_SAVE_CARD_DETAIL] : false;
                $oneClickIsReused = (isset($mollieData[CustomerService::CUSTOM_FIELDS_KEY_MANDATE_ID])) ? (bool) $mollieData[CustomerService::CUSTOM_FIELDS_KEY_MANDATE_ID] : false;
            }

            // create customers for every subscription
            // or if we don't have a guest and our feature is enabled
            $createCustomer = false;

            // subscription requires it
            if ($isSubscription) {
                $createCustomer = true;
            }

            // if real customer, and setting is enabled
            if (! $customer->getGuest() && $settings->createCustomersAtMollie()) {
                $createCustomer = true;
            }

            // if we have a customer that wants to save a mandate or reuse it, and its no guest and our setting is also enabled
            // then create a customer on the mollie side
            if (($oneClickShouldSaveCard || $oneClickIsReused) && ! $customer->getGuest() && $settings->isOneClickPaymentsEnabled()) {
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
            // TODO do we really need to catch this? shouldnt it fail fast?

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
     * @throws ApiException|MollieOrderCancelledException|MollieOrderExpiredException
     */
    private function handleNextPaymentAttempt(OrderEntity $order, string $swOrderTransactionID, OrderAttributes $orderCustomFields, string $mollieOrderId, string $paymentMethod, PaymentTransactionStruct $transactionStruct, SalesChannelContext $salesChannelContext, PaymentHandler $paymentHandler): MolliePaymentPrepareData
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

        // now create a new payment attempt for the order
        // we should get a Mollie payment object as a result.
        $payment = $this->mollieGateway->createOrReusePayment(
            $mollieOrderId,
            $paymentMethod,
            $swOrderTransactionID,
            $paymentHandler,
            $order,
            $customer,
            $salesChannelContext
        );

        // somehow it can be that the status is already APPROVED and our checkoutURL is thus empty.
        // This usually happens for Apple Pay and non-3d-secure CCs, but I don't know why it should happen in a second payment attempt?.
        // anyway, in that case we need to immediately return to the finish page
        if (MolliePaymentStatus::isApprovedStatus($payment->status) && empty($payment->getCheckoutUrl())) {
            return new MolliePaymentPrepareData($transactionStruct->getReturnUrl(), $mollieOrderId);
        }

        if (empty($payment->getCheckoutUrl())) {
            throw new PaymentUrlException($transactionStruct->getOrderTransactionId(), "Couldn't get Mollie payment CheckoutURL for " . $payment->id);
        }

        // save custom fields because shopware return url could have changed
        // e.g. if changedPayment Parameter has to be added the shopware payment token changes
        $orderCustomFields->setMolliePaymentUrl($payment->getCheckoutUrl());
        $this->updaterOrderCustomFields->updateOrder($order->getId(), $orderCustomFields, $salesChannelContext->getContext());

        return new MolliePaymentPrepareData($payment->getCheckoutUrl(), $mollieOrderId);
    }

    /**
     * @throws \Exception
     */
    private function createMollieOrder(OrderEntity $orderEntity, string $paymentMethod, PaymentTransactionStruct $transactionStruct, SalesChannelContext $salesChannelContext, PaymentHandler $paymentHandler): MolliePaymentData
    {
        $salesChannelID = $orderEntity->getSalesChannelId();

        if ($paymentHandler instanceof PosPayment) {
            $params = $this->orderBuilder->buildPaymentsPayload(
                $orderEntity,
                $transactionStruct->getOrderTransactionId(),
                $paymentMethod,
                $salesChannelContext,
                $paymentHandler
            );

            // add our additional custom terminalID parameter
            $params['terminalId'] = $paymentHandler->getTerminalId();

            $molliePayment = $this->mollieGateway->createPayment($params, $salesChannelID);

            $changeStatusUrl = '';

            if (property_exists($molliePayment->_links, 'changePaymentState')) {
                $changeStatusUrl = (string) $molliePayment->_links->changePaymentState->href;
            }

            return new MolliePaymentData(
                $molliePayment->id,
                (string) $molliePayment->getCheckoutUrl(),
                [],
                $changeStatusUrl
            );
        }

        $params = $this->orderBuilder->buildOrderPayload(
            $orderEntity,
            $transactionStruct->getOrderTransactionId(),
            $paymentMethod,
            $salesChannelContext,
            $paymentHandler
        );

        $mollieOrder = $this->mollieGateway->createOrder(
            $params,
            $salesChannelID,
            $salesChannelContext
        );

        /** @var OrderLine[] $orderLines */
        $orderLines = $mollieOrder->lines;

        return new MolliePaymentData(
            $mollieOrder->id,
            (string) $mollieOrder->getCheckoutUrl(),
            $orderLines,
            ''
        );
    }
}
