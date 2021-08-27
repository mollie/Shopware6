<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Exception\CouldNotCreateMollieCustomerException;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Kiener\MolliePayments\Exception\PaymentUrlException;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderBuilder;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\Order\UpdateOrderLineItems;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\UpdateOrderCustomFields;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Mollie\Api\Resources\Order as MollieOrder;
use Monolog\Logger;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
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
    private $orderApiService;
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
    private $updateOrderCustomFields;
    /**
     * @var UpdateOrderLineItems
     */
    private $updateOrderLineItems;
    /**
     * @var LoggerService
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
     * @param LoggerService $logger
     */
    public function __construct(
        OrderDataExtractor      $extractor,
        MollieOrderBuilder      $orderBuilder,
        OrderService            $orderService,
        Order                   $orderApiService,
        CustomerService         $customerService,
        SettingsService         $settingsService,
        UpdateOrderCustomFields $updateOrderCustomFields,
        UpdateOrderLineItems    $updateOrderLineItems,
        LoggerService           $logger
    )
    {
        $this->extractor = $extractor;
        $this->orderBuilder = $orderBuilder;
        $this->orderService = $orderService;
        $this->orderApiService = $orderApiService;
        $this->customerService = $customerService;
        $this->settingsService = $settingsService;
        $this->updateOrderCustomFields = $updateOrderCustomFields;
        $this->updateOrderLineItems = $updateOrderLineItems;
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
     * @return string
     */
    public function preparePayProcessAtMollie(
        string                        $paymentMethod,
        AsyncPaymentTransactionStruct $transactionStruct,
        SalesChannelContext           $salesChannelContext,
        PaymentHandler                $paymentHandler
    ): string
    {
        // get order with all needed associations
        $order = $this->orderService->getOrder($transactionStruct->getOrder()->getId(), $salesChannelContext->getContext());

        if (!$order instanceof OrderEntity) {
            throw new OrderNotFoundException($transactionStruct->getOrder()->getOrderNumber() ?? $transactionStruct->getOrder()->getId());
        }

        $customFields = $order->getCustomFields() ?? [];
        $customFieldsStruct = new MollieOrderCustomFieldsStruct($customFields);
        $customFieldsStruct->setTransactionReturnUrl($transactionStruct->getReturnUrl());
        $mollieOrderId = $customFieldsStruct->getMollieOrderId();

        // do another payment if mollie order could be found
        if (!empty($mollieOrderId)) {
            $this->logger->addDebugEntry(
                sprintf('Found an existing mollie order with id %s.', $mollieOrderId),
                $salesChannelContext->getSalesChannel()->getId(),
                $salesChannelContext->getContext()
            );

            $payment = $this->orderApiService->createOrReusePayment($mollieOrderId, $paymentMethod, $salesChannelContext);

            // if direct payment return to success page
            if (MolliePaymentStatus::isApprovedStatus($payment->status) && empty($payment->getCheckoutUrl())) {

                return $transactionStruct->getReturnUrl();
            }

            $url = $payment->getCheckoutUrl();

            if (empty($url)) {
                throw new PaymentUrlException(
                    $transactionStruct->getOrderTransaction()->getId(),
                    "Couldn't get mollie payment checkout url"
                );
            }

            $customFieldsStruct->setMolliePaymentUrl($url);
            // save customfields because shopware return url could have changed
            // e.g. if changedPayment Parameter has to be added the shopware payment token changes
            $this->updateOrderCustomFields->updateOrder($order->getId(), $customFieldsStruct, $salesChannelContext);

            return $url;
        }

        try {
            $this->createCustomerAtMollie($order, $salesChannelContext);
        } catch (CouldNotCreateMollieCustomerException | CustomerCouldNotBeFoundException $e) {
            $this->logger->addEntry(
                $e->getMessage(),
                $salesChannelContext->getContext(),
                $e,
                ['order' => $order],
                Logger::ERROR
            );
        }


        // build new mollie order array
        $mollieOrderArray = $this->orderBuilder->build(
            $order,
            $transactionStruct->getOrderTransaction()->getId(),
            $paymentMethod,
            $transactionStruct->getReturnUrl(),
            $salesChannelContext,
            $paymentHandler
        );

        $this->logger->addDebugEntry(
            'Created order array for mollie',
            $salesChannelContext->getSalesChannel()->getId(),
            $salesChannelContext->getContext(),
            $mollieOrderArray
        );

        // create new order at mollie
        $mollieOrder = $this->orderApiService->createOrder($mollieOrderArray, $order->getSalesChannelId(), $salesChannelContext);

        if ($mollieOrder instanceof MollieOrder) {
            $customFieldsStruct->setMollieOrderId($mollieOrder->id);
            $customFieldsStruct->setMolliePaymentUrl($mollieOrder->getCheckoutUrl());

            $this->updateOrderCustomFields->updateOrder($order->getId(), $customFieldsStruct, $salesChannelContext);
            $this->updateOrderLineItems->updateOrderLineItems($mollieOrder, $salesChannelContext);
        }

        return $customFieldsStruct->getMolliePaymentUrl() ?? $customFieldsStruct->getTransactionReturnUrl() ?? $transactionStruct->getReturnUrl();
    }

    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $salesChannelContext
     * @throws CouldNotCreateMollieCustomerException
     * @throws CustomerCouldNotBeFoundException
     */
    public function createCustomerAtMollie(OrderEntity $order, SalesChannelContext $salesChannelContext): void
    {
        $customer = $this->extractor->extractCustomer($order, $salesChannelContext);

        // Create a Mollie customer if settings allow it and the customer is not a guest.
        if (!$customer->getGuest() && $this->settingsService->getSettings(
                $salesChannelContext->getSalesChannel()->getId()
            )->createCustomersAtMollie()) {

            $this->customerService->createMollieCustomer(
                $customer->getId(),
                $salesChannelContext->getSalesChannel()->getId(),
                $salesChannelContext->getContext()
            );
        }
    }
}
