<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Exception\PaymentUrlException;
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
        $customFields = $order->getCustomFields() ?? [];
        $customFieldsStruct = new MollieOrderCustomFieldsStruct($customFields);
        $customFieldsStruct->setTransactionReturnUrl($transactionStruct->getReturnUrl());
        $mollieOrderId = $customFieldsStruct->getMollieOrderId();

        // do another payment if mollie order could be found
        if (!empty($mollieOrderId)) {
            $payment = $this->apiOrderService->createOrReusePayment($mollieOrderId, $paymentMethod, $salesChannelContext);

            // if direct payment return to success page
            if ($payment->isPaid()) {
                return $transactionStruct->getReturnUrl();
            }

            $url = $payment->getCheckoutUrl();

            if (empty($url)) {
                throw new PaymentUrlException(
                    $transactionStruct->getOrderTransaction()->getId(),
                    "Couldn't get mollie payment checkout url"
                );
            }

            return $url;
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
