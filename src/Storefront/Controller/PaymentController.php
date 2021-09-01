<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Exception;
use Kiener\MolliePayments\Event\PaymentPageFailEvent;
use Kiener\MolliePayments\Event\PaymentPageRedirectEvent;
use Kiener\MolliePayments\Exception\CouldNotFetchTransaction;
use Kiener\MolliePayments\Exception\MissingMollieOrderId;
use Kiener\MolliePayments\Exception\MissingOrderInTransactionException;
use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeFetched;
use Kiener\MolliePayments\Facade\MollieOrderPaymentFlow;
use Kiener\MolliePayments\Factory\CompatibilityGatewayFactory;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Gateway\CompatibilityGatewayInterface;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\Order\OrderStateService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\TransactionService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Monolog\Logger;
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

class PaymentController extends StorefrontController
{
    /** @var RouterInterface */
    private $router;

    /** @var CompatibilityGatewayInterface */
    private $compatibilityGateway;

    /** @var MollieApiFactory */
    private $apiFactory;

    /** @var BusinessEventDispatcher */
    private $eventDispatcher;

    /** @var OrderStateService */
    private $orderStateService;

    /** @var SettingsService */
    private $settingsService;

    /** @var TransactionService */
    private $transactionService;

    /** @var LoggerService */
    private $logger;

    /** @var TransactionTransitionServiceInterface */
    private $transactionTransitionService;

    /** @var MollieOrderPaymentFlow */
    private $molliePaymentFlow;

    public function __construct(
        RouterInterface $router,
        CompatibilityGatewayFactory $compatibilityGatewayFactory,
        MollieApiFactory $apiFactory,
        BusinessEventDispatcher $eventDispatcher,
        OrderStateService $orderStateService,
        SettingsService $settingsService,
        TransactionService $transactionService,
        LoggerService $logger,
        TransactionTransitionServiceInterface $transactionTransitionService,
        MollieOrderPaymentFlow $molliePaymentFlow
    )
    {
        $this->router = $router;
        $this->apiFactory = $apiFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->orderStateService = $orderStateService;
        $this->settingsService = $settingsService;
        $this->transactionService = $transactionService;
        $this->logger = $logger;
        $this->transactionTransitionService = $transactionTransitionService;
        $this->molliePaymentFlow = $molliePaymentFlow;

        $this->compatibilityGateway = $compatibilityGatewayFactory->create();
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/payment/{transactionId}", defaults={"csrf_protected"=false}, name="frontend.mollie.payment",
     *                                           options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param SalesChannelContext $salesChannelContext
     * @param                     $transactionId
     *
     * @return Response|RedirectResponse
     * @throws ApiException
     */
    public function payment(SalesChannelContext $salesChannelContext, $transactionId): ?Response
    {
        /** @var MollieSettingStruct $settings */
        $settings = $this->settingsService->getSettings($salesChannelContext->getSalesChannel()->getId());

        // Add a message to the log that the webhook has been triggered.
        if ($settings->isDebugMode()) {
            $this->logger->addDebugEntry(
                sprintf('Payment return for transaction %s is triggered.', $transactionId),
                $salesChannelContext->getSalesChannel()->getId(),
                $salesChannelContext->getContext(),
                [
                    'transactionId' => $transactionId,
                ]
            );
        }

        /**
         * Get the transaction from the order transaction repository. With the
         * transaction we can fetch the order from the database.
         */
        $transaction = $this->transactionService->getTransactionById(
            $transactionId,
            null,
            $salesChannelContext->getContext()
        );

        if (!$transaction instanceof OrderTransactionEntity) {
            $this->logger->addEntry(
                sprintf('Transaction with id %s could not be read from database', $transactionId),
                $salesChannelContext->getContext(),
                null,
                null,
                Logger::CRITICAL
            );

            throw new CouldNotFetchTransaction($transactionId);
        }

        $order = $transaction->getOrder();

        if (!$order instanceof OrderEntity) {
            $this->logger->addEntry(
                sprintf('Could not fetch order from transaction with id %s', $transactionId),
                $salesChannelContext->getContext(),
                null,
                null,
                Logger::CRITICAL
            );

            throw new MissingOrderInTransactionException($transactionId);
        }

        $customFieldArray = $order->getCustomFields() ?? [];

        $customFields = new MollieOrderCustomFieldsStruct($customFieldArray);

        $mollieOrderId = $customFields->getMollieOrderId();

        if (empty($mollieOrderId)) {
            $this->logger->addEntry(
                sprintf('Could not fetch mollie order id from order with number %s', $order->getOrderNumber()),
                $salesChannelContext->getContext(),
                null,
                null,
                Logger::CRITICAL
            );

            throw new MissingMollieOrderId($order->getOrderNumber());
        }

        /** @var Order $mollieOrder */
        try {

            $apiClient = $this->apiFactory->getClient($this->compatibilityGateway->getSalesChannelID($salesChannelContext));

            $mollieOrder = $apiClient->orders->get($mollieOrderId, [
                'embed' => 'payments'
            ]);

        } catch (ApiException $e) {
            $this->logger->addEntry(
                sprintf('Could not fetch order at mollie with id %s', $mollieOrderId),
                $salesChannelContext->getContext(),
                null,
                null,
                Logger::CRITICAL
            );

            throw new MollieOrderCouldNotBeFetched($mollieOrderId, [], $e);
        }

        // if configuration is shopware payment flow we could redirect now
        if ($settings->isShopwareFailedPaymentMethod()) {

            return $this->returnRedirect($salesChannelContext, $customFields->getTransactionReturnUrl(), $order, $mollieOrder);
        }

        if (!$this->molliePaymentFlow->process($transaction, $order, $mollieOrder, $salesChannelContext)) {

            return $this->returnFailedRedirect($salesChannelContext, $mollieOrder->getCheckoutUrl(), $order, $mollieOrder, $transactionId);
        }

        return $this->returnRedirect($salesChannelContext, $customFields->getTransactionReturnUrl(), $order, $mollieOrder);
    }

    private function returnRedirect(SalesChannelContext $salesChannelContext, string $redirectUrl, OrderEntity $order, Order $mollieOrder): Response
    {
        $paymentPageRedirectEvent = new PaymentPageRedirectEvent(
            $salesChannelContext->getContext(),
            $order,
            $mollieOrder,
            $salesChannelContext->getSalesChannel()->getId(),
            $redirectUrl
        );

        $this->eventDispatcher->dispatch($paymentPageRedirectEvent, $paymentPageRedirectEvent::EVENT_NAME);

        return new RedirectResponse($redirectUrl);
    }

    private function returnFailedRedirect(SalesChannelContext $salesChannelContext, string $redirectUrl, OrderEntity $order, Order $mollieOrder, string $transactionId): Response
    {
        $paymentPageFailEvent = new PaymentPageFailEvent(
            $salesChannelContext->getContext(),
            $order,
            $mollieOrder,
            $salesChannelContext->getSalesChannel()->getId(),
            $redirectUrl
        );

        $this->eventDispatcher->dispatch($paymentPageFailEvent, $paymentPageFailEvent::EVENT_NAME);

        return $this->renderStorefront('@Storefront/storefront/page/checkout/payment/failed.html.twig', [
            'redirectUrl' => $this->router->generate('frontend.mollie.payment.retry', [
                'transactionId' => $transactionId,
                'redirectUrl' => urlencode($redirectUrl),
            ]),
            'displayUrl' => $redirectUrl,
        ]);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/payment/retry/{transactionId}", defaults={"csrf_protected"=false},
     *                                                               name="frontend.mollie.payment.retry",
     *                                                               options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param SalesChannelContext $context
     * @param                     $transactionId
     * @return Response|RedirectResponse
     * @throws Exception
     */
    public function retry(SalesChannelContext $context, $transactionId): RedirectResponse
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
         * retrieve the Mollie ID from it's custom fields and fetch the payment
         * status from Mollie's Orders API.
         *
         * @var OrderEntity $order
         */
        if ($transaction !== null) {
            $order = $transaction->getOrder();
        }

        // Throw an error if the order is not found
        if (!isset($order)) {
            throw new OrderNotFoundException($transaction->getOrderId());
        }

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
}
