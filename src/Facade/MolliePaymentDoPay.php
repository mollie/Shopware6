<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Exception\InvalidMollieOrderException;
use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeCancelledException;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderBuilder;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\Order as ApiOrderService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\UpdateOrderCustomFields;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
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
     * @param ApiOrderService $apiOrderService
     * @param EntityRepositoryInterface $orderRepository
     * @param MollieOrderBuilder $orderBuilder
     * @param OrderService $orderService
     * @param ApiOrderService $apiOrder
     * @param UpdateOrderCustomFields $updateOrderCustomFields
     * @param LoggerService $logger
     */
    public function __construct(
        ApiOrderService $apiOrderService,
        EntityRepositoryInterface $orderRepository,
        MollieOrderBuilder $orderBuilder,
        OrderService $orderService,
        Order $apiOrder,
        UpdateOrderCustomFields $updateOrderCustomFields,
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
    }

    public function getPaymentUrl(
        string $paymentMethod,
        AsyncPaymentTransactionStruct $transactionStruct,
        SalesChannelContext $salesChannelContext,
        PaymentHandler $paymentHandler
    ): string
    {
        $order = $this->orderService->getOrder($transactionStruct->getOrder()->getId(), $salesChannelContext->getContext());
        $customFields = new MollieOrderCustomFieldsStruct($order->getCustomFields());

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

            //@todo save customFields here !
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

        // save custom fields orderid of mollie
        $this->updateOrderCustomFields->updateOrder($order, $mollieOrder, $transactionStruct->getReturnUrl(), $salesChannelContext);

        // return mollie return url
        $mollieCheckoutUrl = $mollieOrder->getCheckoutUrl();

        if (is_null($mollieCheckoutUrl)) {
            $this->logger->addEntry(
                sprintf(
                    'Could not get mollie payment url (Order: %s, MollieOrder: %s)',
                    $order->getOrderNumber(),
                    $mollieOrder->id
                ),
                $salesChannelContext->getContext(),
                null,
                null,
                Logger::CRITICAL
            );

            throw new InvalidMollieOrderException();
        }

        return $mollieCheckoutUrl;
    }
}
