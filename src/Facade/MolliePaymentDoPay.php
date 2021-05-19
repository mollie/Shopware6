<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeCancelledException;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderBuilder;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\Order as ApiOrderService;
use Kiener\MolliePayments\Service\Order\UpdateOrderLineItems;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\UpdateOrderCustomFields;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Mollie\Api\Resources\Order as MollieOrder;
use Monolog\Logger;
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
    private MollieOrderBuilder $orderBuilder;
    /**
     * @var OrderService
     */
    private OrderService $orderService;
    /**
     * @var ApiOrderService
     */
    private ApiOrderService $apiOrder;
    /**
     * @var UpdateOrderCustomFields
     */
    private UpdateOrderCustomFields $updateOrderCustomFields;
    /**
     * @var UpdateOrderLineItems
     */
    private UpdateOrderLineItems $updateOrderLineItems;


    /**
     * @param ApiOrderService $apiOrderService
     * @param EntityRepositoryInterface $orderRepository
     * @param MollieOrderBuilder $orderBuilder
     * @param OrderService $orderService
     * @param ApiOrderService $apiOrder
     * @param UpdateOrderCustomFields $updateOrderCustomFields
     * @param UpdateOrderLineItems $updateOrderLineItems
     * @param LoggerService $logger
     */
    public function __construct(
        ApiOrderService $apiOrderService,
        EntityRepositoryInterface $orderRepository,
        MollieOrderBuilder $orderBuilder,
        OrderService $orderService,
        Order $apiOrder,
        UpdateOrderCustomFields $updateOrderCustomFields,
        UpdateOrderLineItems $updateOrderLineItems,
        LoggerService $logger
    )
    {
        $this->apiOrderService = $apiOrderService;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->orderBuilder = $orderBuilder;
        $this->orderService = $orderService;
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
        string $paymentMethod,
        AsyncPaymentTransactionStruct $transactionStruct,
        SalesChannelContext $salesChannelContext,
        PaymentHandler $paymentHandler
    ): string
    {
        // get order with all needed associations
        $order = $this->orderService->getOrder($transactionStruct->getOrder()->getId(), $salesChannelContext->getContext());
        $customFields = new MollieOrderCustomFieldsStruct($order->getCustomFields());
        $customFields->setTransactionReturnUrl($transactionStruct->getReturnUrl());

        // cancel existing mollie order if we may find one, unfortunately we may not reuse an existing mollie order if we need another payment method
        $mollieOrderId = $customFields->getMollieOrderId();

        if (!empty($mollieOrderId)) {
            // cancel previous payment at mollie
            try {
                $this->apiOrderService->cancelOrder($mollieOrderId, $salesChannelContext);
            } catch (MollieOrderCouldNotBeCancelledException $e) {
                // we do nothing here. This should not happen, but if it happens it will not harm
                $this->logger->addEntry(
                    $e->getMessage(),
                    $salesChannelContext->getContext(),
                    $e,
                    ['shopwareOrderNumber' => $order->getOrderNumber()],
                    Logger::WARNING
                );
            }

            // even if cancel previous order has not been successful, we will not use this order again
            $customFields->setMollieOrderId(null);
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

        // create new order at mollie
        $mollieOrder = $this->apiOrder->createOrder($mollieOrderArray, $salesChannelContext);

        if ($mollieOrder instanceof MollieOrder) {
            $customFields->setMollieOrderId($mollieOrder->getId());
            $customFields->setMolliePaymentUrl($mollieOrder->getCheckoutUrl());

            $this->updateOrderCustomFields->updateOrder($order->getId(), $customFields, $salesChannelContext);
            $this->updateOrderLineItems->updateOrderLineItems($mollieOrder, $salesChannelContext);
        }

        return $customFields->getMolliePaymentUrl() ?? $customFields->getTransactionReturnUrl() ?? $transactionStruct->getReturnUrl();
    }
}
