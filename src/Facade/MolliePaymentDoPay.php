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
use Kiener\MolliePayments\Service\MollieApi\Order as ApiOrderService;
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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MolliePaymentDoPay
{
    /**
     * @var ApiOrderService
     */
    private $apiOrderService;
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;
    /**
     * @var LoggerService
     */
    private $logger;
    /**
     * @var MollieOrderBuilder
     */
    private $orderBuilder;
    /**
     * @var OrderService
     */
    private $orderService;
    /**
     * @var ApiOrderService
     */
    private $apiOrder;
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
     * @param ApiOrderService $apiOrderService
     * @param EntityRepositoryInterface $orderRepository
     * @param MollieOrderBuilder $orderBuilder
     * @param OrderService $orderService
     * @param ApiOrderService $apiOrder
     * @param CustomerService $customerService
     * @param SettingsService $settingsService
     * @param UpdateOrderCustomFields $updateOrderCustomFields
     * @param UpdateOrderLineItems $updateOrderLineItems
     * @param LoggerService $logger
     */
    public function __construct(
        ApiOrderService           $apiOrderService,
        EntityRepositoryInterface $orderRepository,
        MollieOrderBuilder        $orderBuilder,
        OrderService              $orderService,
        Order                     $apiOrder,
        CustomerService           $customerService,
        SettingsService           $settingsService,
        UpdateOrderCustomFields   $updateOrderCustomFields,
        UpdateOrderLineItems      $updateOrderLineItems,
        LoggerService             $logger
    )
    {
        $this->apiOrderService = $apiOrderService;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->orderBuilder = $orderBuilder;
        $this->orderService = $orderService;
        $this->customerService = $customerService;
        $this->settingsService = $settingsService;
        $this->apiOrder = $apiOrder;
        $this->updateOrderCustomFields = $updateOrderCustomFields;
        $this->updateOrderLineItems = $updateOrderLineItems;
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

            $payment = $this->apiOrderService->createOrReusePayment($mollieOrderId, $paymentMethod, $salesChannelContext);

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

        // Create a Mollie customer if settings allow it and the customer is not a guest.
        if ($this->settingsService->getSettings($salesChannelContext->getSalesChannelId())->createCustomersAtMollie() &&
            !$order->getOrderCustomer()->getCustomer()->getGuest()) {

            try {
                $this->customerService->createMollieCustomer(
                    $order->getOrderCustomer()->getCustomer()->getId(),
                    $salesChannelContext->getSalesChannelId(),
                    $salesChannelContext->getContext()
                );
            } catch (CouldNotCreateMollieCustomerException | CustomerCouldNotBeFoundException $e) {
                $this->logger->addEntry(
                    $e->getMessage(),
                    $salesChannelContext->getContext(),
                    $e,
                    [],
                    Logger::ERROR
                );
            }
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
        $mollieOrder = $this->apiOrder->createOrder($mollieOrderArray, $order->getSalesChannelId(), $salesChannelContext);

        if ($mollieOrder instanceof MollieOrder) {
            $customFieldsStruct->setMollieOrderId($mollieOrder->id);
            $customFieldsStruct->setMolliePaymentUrl($mollieOrder->getCheckoutUrl());

            $this->updateOrderCustomFields->updateOrder($order->getId(), $customFieldsStruct, $salesChannelContext);
            $this->updateOrderLineItems->updateOrderLineItems($mollieOrder, $salesChannelContext);
        }

        return $customFieldsStruct->getMolliePaymentUrl() ?? $customFieldsStruct->getTransactionReturnUrl() ?? $transactionStruct->getReturnUrl();
    }
}
