<?php

namespace Kiener\MolliePayments\Controller\Storefront\Payment;

use Exception;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactoryInterface;
use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGatewayInterface;
use Kiener\MolliePayments\Event\PaymentPageFailEvent;
use Kiener\MolliePayments\Exception\CouldNotFetchTransactionException;
use Kiener\MolliePayments\Exception\MissingMollieOrderIdException;
use Kiener\MolliePayments\Exception\MissingOrderInTransactionException;
use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeFetchedException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\Order\OrderStateService;
use Kiener\MolliePayments\Service\TransactionService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Event\BusinessEventDispatcher;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class MollieFailureControllerBase extends StorefrontController
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
     * @param RouterInterface $router
     * @param CompatibilityGatewayInterface $compatibilityGateway
     * @param MollieApiFactory $apiFactory
     * @param OrderStateService $orderStateService
     * @param TransactionService $transactionService
     * @param LoggerInterface $logger
     * @param TransactionTransitionServiceInterface $transactionTransitionService
     * @param FlowBuilderFactoryInterface $flowBuilderFactory
     * @throws Exception
     */
    public function __construct(RouterInterface $router, CompatibilityGatewayInterface $compatibilityGateway, MollieApiFactory $apiFactory, OrderStateService $orderStateService, TransactionService $transactionService, LoggerInterface $logger, TransactionTransitionServiceInterface $transactionTransitionService, FlowBuilderFactoryInterface $flowBuilderFactory)
    {
        $this->router = $router;
        $this->compatibilityGateway = $compatibilityGateway;
        $this->apiFactory = $apiFactory;
        $this->orderStateService = $orderStateService;
        $this->transactionService = $transactionService;
        $this->logger = $logger;
        $this->transactionTransitionService = $transactionTransitionService;

        $this->eventDispatcher = $flowBuilderFactory->createDispatcher();
    }

    /**
     * @Route("/mollie/payment/failed/{transactionId}", defaults={"csrf_protected"=false}, name="frontend.mollie.payment-failed", options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param SalesChannelContext $salesChannelContext
     * @param string $transactionId
     *
     * @throws ApiException
     * @return null|Response
     * @return RedirectResponse|Response
     */
    public function paymentFailedAction(SalesChannelContext $salesChannelContext, string $transactionId): ?Response
    {
        /**
         * Get the transaction from the order transaction repository. With the
         * transaction we can fetch the order from the database.
         */
        $transaction = $this->transactionService->getTransactionById($transactionId, null, $salesChannelContext->getContext());

        if (!$transaction instanceof OrderTransactionEntity) {
            $this->logger->critical(sprintf('Transaction with id %s could not be read from database', $transactionId));
            throw new CouldNotFetchTransactionException($transactionId);
        }

        $order = $transaction->getOrder();

        // TODO: Refactor to use Service/OrderService::getOrder if $order does not exist.
        if (!$order instanceof OrderEntity) {
            $this->logger->critical(sprintf('Could not fetch order from transaction with id %s', $transactionId));
            throw new MissingOrderInTransactionException($transactionId);
        }

        $orderAttributes = new OrderAttributes($order);

        $this->logger->debug(
            'Customer is returning to Shopware for order: ' . $order->getOrderNumber() . ' and Mollie ID: ' . $orderAttributes->getMollieOrderId(),
            [
            ]
        );


        // TODO: Possibly refactor to use Service/OrderService::getMollieOrderId
        $customFieldArray = $order->getCustomFields() ?? [];

        $customFields = new OrderAttributes($order);

        $mollieOrderId = $customFields->getMollieOrderId();

        if (empty($mollieOrderId)) {
            $this->logger->critical(sprintf('Could not fetch mollie order id from order with number %s', $order->getOrderNumber()));
            throw new MissingMollieOrderIdException((string)$order->getOrderNumber());
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
            (string)$mollieOrder->getCheckoutUrl(),
            $order,
            $mollieOrder,
            $transactionId
        );
    }


    /**
     * @Route("/mollie/payment/retry/{transactionId}", defaults={"csrf_protected"=false}, name="frontend.mollie.payment.retry", options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param SalesChannelContext $context
     * @param string $transactionId
     * @throws Exception
     * @return RedirectResponse
     */
    public function retry(SalesChannelContext $context, string $transactionId): RedirectResponse
    {
        # keep compatible to older Shopware versions by avoiding
        # the Parameter Bag
        $redirectUrl = (string)$_GET['redirectUrl'];

        /** @var string $redirectUrl */
        $redirectUrl = urldecode($redirectUrl);

        if (!filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
            throw new Exception('The redirect URL is invalid.');
        }

        /**
         * Get the transaction from the order transaction repository. With the
         * transaction we can fetch the order from the database.
         *
         * @var OrderTransactionEntity $transaction
         */
        $transaction = $this->transactionService->getTransactionById(
            $transactionId,
            null,
            $context->getContext()
        );

        /**
         * Get the order entity from the transaction. With the order entity, we can
         * retrieve the Mollie ID from its custom fields and fetch the payment
         * status from Mollie's Orders API.
         */
        if ($transaction !== null) {
            $order = $transaction->getOrder();
        }

        // Throw an error if the order is not found
        if (!isset($order)) {
            throw new OrderNotFoundException($transaction->getOrderId());
        }

        $orderAttributes = new OrderAttributes($order);

        $this->logger->info(
            'Retry failed payment with Mollie failure mode for order ' . $order->getOrderNumber() . ' and Mollie ID: ' . $orderAttributes->getMollieOrderId(),
            [
                'saleschannel' => $context->getSalesChannel()->getName(),
            ]
        );

        // Reopen the order
        $this->orderStateService->setOrderState(
            $order,
            OrderStates::STATE_OPEN,
            $context->getContext()
        );

        // If we redirect to the payment screen, set the transaction to in progress
        $this->transactionTransitionService->processTransaction($transaction, $context->getContext());

        return new RedirectResponse($redirectUrl);
    }


    /**
     * @param SalesChannelContext $salesChannelContext
     * @param string $redirectUrl
     * @param OrderEntity $order
     * @param Order $mollieOrder
     * @param string $transactionId
     * @return Response
     */
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
                'redirectUrl' => urlencode($redirectUrl),
            ]),
            'displayUrl' => $redirectUrl,
        ]);
    }
}
