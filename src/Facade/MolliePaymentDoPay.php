<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Exception\CouldNotCreateMollieCustomerException;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Kiener\MolliePayments\Exception\PaymentUrlException;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderBuilder;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\Order\UpdateOrderLineItems;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\UpdateOrderCustomFields;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Kiener\MolliePayments\Struct\MolliePaymentPrepareData;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order as MollieOrder;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

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
     * @param LoggerInterface $logger
     */
    public function __construct(OrderDataExtractor $extractor, MollieOrderBuilder $orderBuilder, OrderService $orderService, Order $orderApiService, CustomerService $customerService, SettingsService $settingsService, UpdateOrderCustomFields $updateOrderCustomFields, UpdateOrderLineItems $updateOrderLineItems, LoggerInterface $logger)
    {
        $this->extractor = $extractor;
        $this->orderBuilder = $orderBuilder;
        $this->orderService = $orderService;
        $this->mollieGateway = $orderApiService;
        $this->customerService = $customerService;
        $this->settingsService = $settingsService;
        $this->updaterOrderCustomFields = $updateOrderCustomFields;
        $this->updaterLineItemCustomFields = $updateOrderLineItems;
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
     * @return MolliePaymentPrepareData
     * @throws ApiException
     */
    public function startMolliePayment(string $paymentMethod, AsyncPaymentTransactionStruct $transactionStruct, SalesChannelContext $salesChannelContext, PaymentHandler $paymentHandler): MolliePaymentPrepareData
    {
        # this is the current order transaction
        # of this payment attempt in Shopware
        $swOrderTransactionID = $transactionStruct->getOrderTransaction()->getId();


        // get order with all needed associations
        $order = $this->orderService->getOrder($transactionStruct->getOrder()->getId(), $salesChannelContext->getContext());

        # build our custom fields
        # object for this order
        $orderCustomFields = new MollieOrderCustomFieldsStruct($order->getCustomFields() ?? []);
        $orderCustomFields->setTransactionReturnUrl($transactionStruct->getReturnUrl());

        # extract the main Mollie Order ID "ord_xyz" that
        # we are working on in this case. this is empty for first orders
        # but filled if we do another payment attempt for an existing order.
        $mollieOrderId = $orderCustomFields->getMollieOrderId();


        # now let's check if we have another payment attempt for an existing order.
        # this is the case, if we already have a Mollie Order ID in our custom fields.
        # in this case, we just add a new payment (transaction) to the existing order in Mollie.
        if (!empty($mollieOrderId)) {
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

        # now update our custom struct values
        # and immediately set our Mollie Order ID and more
        $orderCustomFields->setMollieOrderId($mollieOrder->id);
        $orderCustomFields->setMolliePaymentUrl($mollieOrder->getCheckoutUrl());

        # we save that data in both, the order and
        # the order line items
        $this->updaterOrderCustomFields->updateOrder($order->getId(), $orderCustomFields, $salesChannelContext);
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

            $customer = $this->extractor->extractCustomer($order, $salesChannelContext);

            // Create a Mollie customer if settings allow it and the customer is not a guest.
            if (!$customer->getGuest() && $this->settingsService->getSettings($salesChannelContext->getSalesChannel()->getId())->createCustomersAtMollie()) {

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
     * @param MollieOrderCustomFieldsStruct $orderCustomFields
     * @param string $mollieOrderId
     * @param string $paymentMethod
     * @param AsyncPaymentTransactionStruct $transactionStruct
     * @param SalesChannelContext $salesChannelContext
     * @param PaymentHandler $paymentHandler
     * @return MolliePaymentPrepareData
     * @throws ApiException
     */
    private function handleNextPaymentAttempt(OrderEntity $order, string $swOrderTransactionID, MollieOrderCustomFieldsStruct $orderCustomFields, string $mollieOrderId, string $paymentMethod, AsyncPaymentTransactionStruct $transactionStruct, SalesChannelContext $salesChannelContext, PaymentHandler $paymentHandler): MolliePaymentPrepareData
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
        $this->updaterOrderCustomFields->updateOrder($order->getId(), $orderCustomFields, $salesChannelContext);

        return new MolliePaymentPrepareData($payment->getCheckoutUrl(), $mollieOrderId);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param string $paymentMethod
     * @param AsyncPaymentTransactionStruct $transactionStruct
     * @param SalesChannelContext $salesChannelContext
     * @param PaymentHandler $paymentHandler
     * @return MollieOrder
     * @throws \Exception
     */
    private function createMollieOrder(OrderEntity $orderEntity, string $paymentMethod, AsyncPaymentTransactionStruct $transactionStruct, SalesChannelContext $salesChannelContext, PaymentHandler $paymentHandler): \Mollie\Api\Resources\Order
    {
        $salesChannelID = $orderEntity->getSalesChannelId();

        $params = $this->orderBuilder->build(
            $orderEntity,
            $transactionStruct->getOrderTransaction()->getId(),
            $paymentMethod,
            $transactionStruct->getReturnUrl(),
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
