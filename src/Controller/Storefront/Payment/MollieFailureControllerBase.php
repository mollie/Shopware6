<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Storefront\Payment;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactoryInterface;
use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGatewayInterface;
use Kiener\MolliePayments\Controller\Storefront\AbstractStoreFrontController;
use Kiener\MolliePayments\Event\PaymentPageFailEvent;
use Kiener\MolliePayments\Exception\CouldNotFetchTransactionException;
use Kiener\MolliePayments\Exception\MissingMollieOrderIdException;
use Kiener\MolliePayments\Exception\MissingOrderInTransactionException;
use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeFetchedException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Kiener\MolliePayments\Service\MollieApi\Order as MollieServiceOrder;
use Kiener\MolliePayments\Service\Order\OrderStateService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\TransactionService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class MollieFailureControllerBase extends AbstractStoreFrontController
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var CompatibilityGatewayInterface
     */
    private $compatibilityGateway;

    /**
     * @var MollieApiFactory
     */
    private $apiFactory;

    /**
     * @var FlowBuilderDispatcherAdapterInterface
     */
    private $eventDispatcher;

    /**
     * @var OrderStateService
     */
    private $orderStateService;

    /**
     * @var TransactionService
     */
    private $transactionService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TransactionTransitionServiceInterface
     */
    private $transactionTransitionService;

    /**
     * @var MollieServiceOrder
     */
    private $mollieOrderService;

    /**
     * @var OrderStatusConverter
     */
    private $orderStatusConverter;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var CustomerService
     */
    private $customerService;

    public function __construct(RouterInterface $router, CompatibilityGatewayInterface $compatibilityGateway, MollieApiFactory $apiFactory, OrderStateService $orderStateService, TransactionService $transactionService, LoggerInterface $logger, TransactionTransitionServiceInterface $transactionTransitionService, FlowBuilderFactoryInterface $flowBuilderFactory, MollieServiceOrder $mollieOrderService, OrderStatusConverter $orderStatusConverter, SettingsService $settingsService, CustomerService $customerService)
    {
        $this->router = $router;
        $this->compatibilityGateway = $compatibilityGateway;
        $this->apiFactory = $apiFactory;
        $this->orderStateService = $orderStateService;
        $this->transactionService = $transactionService;
        $this->logger = $logger;
        $this->transactionTransitionService = $transactionTransitionService;
        $this->mollieOrderService = $mollieOrderService;
        $this->orderStatusConverter = $orderStatusConverter;

        $this->eventDispatcher = $flowBuilderFactory->createDispatcher();
        $this->settingsService = $settingsService;
        $this->customerService = $customerService;
    }

    /**
     * @throws ApiException
     *
     * @return RedirectResponse|Response
     */
    public function paymentFailedAction(SalesChannelContext $salesChannelContext, string $transactionId): ?Response
    {
        /**
         * Get the transaction from the order transaction repository. With the
         * transaction we can fetch the order from the database.
         */
        $transaction = $this->transactionService->getTransactionById($transactionId, null, $salesChannelContext->getContext());

        if (! $transaction instanceof OrderTransactionEntity) {
            $this->logger->critical(sprintf('Transaction with id %s could not be read from database', $transactionId));
            throw new CouldNotFetchTransactionException($transactionId);
        }

        $order = $transaction->getOrder();

        // TODO: Refactor to use Service/OrderService::getOrder if $order does not exist.
        if (! $order instanceof OrderEntity) {
            $this->logger->critical(sprintf('Could not fetch order from transaction with id %s', $transactionId));
            throw new MissingOrderInTransactionException($transactionId);
        }

        $orderAttributes = new OrderAttributes($order);

        $this->logger->debug(
            'Customer is returning to Shopware for order: ' . $order->getOrderNumber() . ' and Mollie ID: ' . $orderAttributes->getMollieOrderId(),
            [
            ]
        );

        $customFields = new OrderAttributes($order);

        // TODO: Possibly refactor to use Service/OrderService::getMollieOrderId
        $mollieOrderId = $customFields->getMollieOrderId();

        if (empty($mollieOrderId)) {
            $this->logger->critical(sprintf('Could not fetch mollie order id from order with number %s', $order->getOrderNumber()));
            throw new MissingMollieOrderIdException((string) $order->getOrderNumber());
        }

        // TODO: Refactor to use Service/MollieApi/Order::getMollieOrder

        try {
            $apiClient = $this->apiFactory->getClient($this->compatibilityGateway->getSalesChannelID($salesChannelContext));

            $mollieOrder = $apiClient->orders->get($mollieOrderId, ['embed' => 'payments']);
        } catch (ApiException $e) {
            $this->logger->critical(sprintf('Could not fetch order at mollie with id %s', $mollieOrderId));
            throw new MollieOrderCouldNotBeFetchedException($mollieOrderId, [], $e);
        }

        return $this->returnFailedRedirect(
            $salesChannelContext,
            (string) $mollieOrder->getCheckoutUrl(),
            $order,
            $mollieOrder,
            $transactionId
        );
    }

    /**
     * @throws \Exception
     */
    public function retry(SalesChannelContext $context, string $transactionId): RedirectResponse
    {
        $transaction = $this->transactionService->getTransactionById($transactionId, null, $context->getContext());

        if ($transaction === null) {
            throw new \Exception('Transaction with ID ' . $transactionId . ' not found');
        }

        $order = $transaction->getOrder();

        if (! $order instanceof OrderEntity) {
            throw new \Exception('Order for transaction with ID ' . $transaction->getOrderId() . ' not found');
        }

        $orderAttributes = new OrderAttributes($order);

        $this->logger->info(
            'Retry failed payment with Mollie failure mode for order ' . $order->getOrderNumber() . ' and Mollie ID: ' . $orderAttributes->getMollieOrderId(),
            [
                'saleschannel' => $context->getSalesChannel()->getName(),
            ]
        );

        // REOPEN the order
        $this->orderStateService->setOrderState($order, OrderStates::STATE_OPEN, $context->getContext());

        // if we redirect to the payment screen, set the transaction to in progress
        $this->transactionTransitionService->processTransaction($transaction, $context->getContext());

        // now fetch the Mollie order
        $mollieOrder = $this->mollieOrderService->getMollieOrder(
            $orderAttributes->getMollieOrderId(),
            $order->getSalesChannelId(),
            [
                'embed' => 'payments',
            ]
        );

        $paymentStatus = $this->orderStatusConverter->getMollieOrderStatus($mollieOrder);

        // if its a failed status, then we have to create a new payment
        // otherwise no payment would exist, and we are not able to redirect to the payment screen
        if (MolliePaymentStatus::isFailedStatus('', $paymentStatus)) {
            $settings = $this->settingsService->getSettings($context->getSalesChannelId());
            $paymentData = [];

            if ($settings->isSubscriptionsEnabled()) {
                /** @var OrderLineItemCollection $lineItems */
                $lineItems = $order->getLineItems();
                /** @var OrderCustomerEntity $customer */
                $customer = $order->getOrderCustomer();
                /** @var string $customerId */
                $customerId = $customer->getCustomerId();

                // mollie customer ID is required for recurring payments, see https://docs.mollie.com/reference/v2/orders-api/create-order-payment
                $mollieCustomerId = $this->customerService->getMollieCustomerId($customerId, $context->getSalesChannelId(), $context->getContext());

                foreach ($lineItems as $lineItem) {
                    $attributes = new OrderLineItemEntityAttributes($lineItem);
                    if ($attributes->isSubscriptionProduct()) {
                        $paymentData['sequenceType'] = 'first';
                        $paymentData['customerId'] = $mollieCustomerId;
                        break;
                    }
                }
            }
            $mollieOrder->createPayment($paymentData);
        }

        $redirectUrl = (string) $orderAttributes->getMolliePaymentUrl();

        if (! filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
            throw new \Exception('The redirect URL is invalid.');
        }

        return new RedirectResponse($redirectUrl);
    }

    private function returnFailedRedirect(SalesChannelContext $salesChannelContext, string $redirectUrl, OrderEntity $order, Order $mollieOrder, string $transactionId): Response
    {
        $orderAttributes = new OrderAttributes($order);

        $this->logger->info(
            'Payment failed with Mollie failure mode for order ' . $order->getOrderNumber() . ' and Mollie ID: ' . $orderAttributes->getMollieOrderId(),
            [
                'saleschannel' => $salesChannelContext->getSalesChannel()->getName(),
            ]
        );

        $paymentPageFailEvent = new PaymentPageFailEvent(
            $salesChannelContext->getContext(),
            $order,
            $mollieOrder,
            $salesChannelContext->getSalesChannel()->getId(),
            $redirectUrl
        );

        $this->eventDispatcher->dispatch($paymentPageFailEvent);

        return $this->renderStorefront('@Storefront/storefront/page/checkout/payment/failed.html.twig', [
            'redirectUrl' => $this->router->generate('frontend.mollie.payment.retry', [
                'transactionId' => $transactionId,
            ]),
            'displayUrl' => $redirectUrl,
        ]);
    }
}
