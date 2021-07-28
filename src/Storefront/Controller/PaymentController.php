<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Exception;
use Kiener\MolliePayments\Event\PaymentPageFailEvent;
use Kiener\MolliePayments\Event\PaymentPageRedirectEvent;
use Kiener\MolliePayments\Facade\MollieOrderPaymentFlow;
use Kiener\MolliePayments\Helper\PaymentStatusHelper;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\Order\OrderStateService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Service\TransactionService;
use Kiener\MolliePayments\Service\Transition\TransactionTransitionServiceInterface;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Struct\MollieOrderCustomFieldsStruct;
use Kiener\MolliePayments\Validator\DoesOpenPaymentExist;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Types\PaymentStatus;
use RuntimeException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
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

    /** @var MollieApiClient */
    private $apiClient;

    /** @var BusinessEventDispatcher */
    private $eventDispatcher;

    /** @var OrderStateService */
    private $orderStateService;

    /** @var PaymentStatusHelper */
    private $paymentStatusHelper;

    /** @var SettingsService */
    private $settingsService;

    /** @var TransactionService */
    private $transactionService;

    /** @var LoggerService */
    private $logger;

    /** @var TransactionTransitionServiceInterface */
    private TransactionTransitionServiceInterface $transactionTransitionService;
    /**
     * @var MollieOrderPaymentFlow
     */
    private MollieOrderPaymentFlow $molliePaymentFlow;

    public function __construct(
        RouterInterface $router,
        MollieApiClient $apiClient,
        BusinessEventDispatcher $eventDispatcher,
        OrderStateService $orderStateService,
        PaymentStatusHelper $paymentStatusHelper,
        SettingsService $settingsService,
        TransactionService $transactionService,
        LoggerService $logger,
        TransactionTransitionServiceInterface $transactionTransitionService,
        MollieOrderPaymentFlow $molliePaymentFlow
    )
    {
        $this->router = $router;
        $this->apiClient = $apiClient;
        $this->eventDispatcher = $eventDispatcher;
        $this->orderStateService = $orderStateService;
        $this->paymentStatusHelper = $paymentStatusHelper;
        $this->settingsService = $settingsService;
        $this->transactionService = $transactionService;
        $this->logger = $logger;
        $this->transactionTransitionService = $transactionTransitionService;
        $this->molliePaymentFlow = $molliePaymentFlow;
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
        $settings = $this->settingsService->getSettings(
            $salesChannelContext->getSalesChannel()->getId(),
            $salesChannelContext->getContext()
        );

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
            //@todo do something here, log and throw an error
        }

        $order = $transaction->getOrder();

        if (!$order instanceof OrderEntity) {
            //@todo do something here, log and throw an error
        }

        $customFieldArray = $order->getCustomFields() ?? [];

        $customFields = new MollieOrderCustomFieldsStruct($customFieldArray);

        $returnUrl = $customFields->getTransactionReturnUrl() ?? '';

        if (empty($returnUrl)) {
            //@todo do something here, log and throw an error
        }

        $mollieOrderId = $customFields->getMollieOrderId();

        if (empty($mollieOrderId)) {
            //@todo do something here, log and throw an error
        }

        /** @var Order $mollieOrder */
        try {
            $mollieOrder = $this->apiClient->orders->get($mollieOrderId, [
                'embed' => 'payments'
            ]);
        } catch (ApiException $e) {
            //@todo do something here, log and throw an error
        }

        // if configuration is shopware payment flow we could redirect now
        if ($settings->isShopwareFailedPaymentMethod()) {

            return $this->returnRedirect($salesChannelContext, $customFields->getTransactionReturnUrl(), $order, $mollieOrder);
        }

        if (!$this->molliePaymentFlow->process()) {

            return $this->returnRedirect($salesChannelContext, $returnUrl, $order, $mollieOrder);
        }

        return $this->renderStorefront('@Storefront/storefront/page/checkout/payment/failed.html.twig', [
            'redirectUrl' => $this->router->generate('frontend.mollie.payment.retry', [
                'transactionId' => $transactionId,
                'redirectUrl' => urlencode($mollieOrder->getCheckoutUrl()),
            ]),
            'displayUrl' => $mollieOrder->getCheckoutUrl()
        ]);
    }

    public function paymentOld(SalesChannelContext $context, $transactionId): ?Response
    {
        $criteria = null;
        $customFields = null;
        $errorMessage = null;
        $mollieOrder = null;
        $mollieOrderId = null;
        $order = null;
        $paymentFailed = false;
        $paymentStatus = null;
        $redirectUrl = null;
        $transaction = null;

        /** @var MollieSettingStruct $settings */
        $settings = $this->settingsService->getSettings($context->getSalesChannel()->getId());

        // Add a message to the log that the webhook has been triggered.
        if ($settings->isDebugMode()) {
            $this->logger->addEntry(
                sprintf('Payment return for transaction %s is triggered.', $transactionId),
                $context->getContext(),
                null,
                [
                    'transactionId' => $transactionId,
                ]
            );
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

        /**
         * Get the custom fields from the order. These custom fields are used to
         * retrieve the order ID of Mollie's order. With this ID, we can fetch the
         * order from Mollie's Orders API.
         *
         * @var $customFields
         */
        if ($order !== null) {
            $customFields = $order->getCustomFields() ?? [];
        } else {
            $errorMessage = $errorMessage ?? 'No order found for transaction with ID ' . $transactionId . '.';
        }

        /**
         * Set the API keys at Mollie based on the current context.
         */
        $this->setApiKeysBySalesChannelContext($context);

        /**
         * With the order ID from the custom fields, we fetch the order from Mollie's
         * Orders API.
         *
         * The transaction return URL is used for redirecting the customer to the checkout
         * finish page.
         *
         * @var $mollieOrder
         */
        if (is_array($customFields)) {
            if (isset($customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS]['order_id'])) {
                /** @var string $mollieOrderId */
                $mollieOrderId = $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS]['order_id'];

                /** @var Order $mollieOrder */
                try {
                    $mollieOrder = $this->apiClient->orders->get($mollieOrderId, [
                        'embed' => 'payments'
                    ]);
                } catch (ApiException $e) {
                    $errorMessage = $errorMessage ?? $e->getMessage();
                }
            }

            //@todo return url steht bereits in custom fields !

            if (isset($customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS]['transactionReturnUrl'])) {
                $redirectUrl = $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS]['transactionReturnUrl'];
            }
        }

        /**
         * The payment status of the order is fetched from Mollie's Orders API. We
         * use this payment status to set the status in Shopware.
         *
         * @todo we only need this if configuration is set to mollie failure webpage. in Shopware standard behaviour there is no need for this
         * refactor all of this
         * normal shopware payment flow => just redirect
         */
        if ($mollieOrder !== null) {
            try {
                $paymentStatus = $this->paymentStatusHelper->processPaymentStatus(
                    $transaction,
                    $order,
                    $mollieOrder,
                    $context->getContext()
                );
            } catch (Exception $e) {
                $errorMessage = $errorMessage ?? $e->getMessage();
            }
        } else {
            $errorMessage = $errorMessage ?? 'No order found in the Orders API with ID ' . $mollieOrderId ?? '<unknown>';
        }

        /**
         * If the payment status is null, no status could be set.
         */
        if ($paymentStatus === null) {
            $errorMessage = $errorMessage ?? 'The payment status has not been set for order with ID ' . $mollieOrderId ?? '<unknown>';
        }

        /**
         * If any errors occurred during the webhook call, we return an error message.
         */
        if ($errorMessage !== null) {
            $this->logger->addEntry(
                $errorMessage,
                $context->getContext(),
                null,
                [
                    'function' => 'payment',
                ]
            );
        }

        if (
            $paymentStatus !== null
            && (
                $paymentStatus === PaymentStatus::STATUS_CANCELED
                || $paymentStatus === PaymentStatus::STATUS_FAILED
            )
            && $this->settingsService->getSettings($context->getSalesChannel()->getId())
                ->isShopwareFailedPaymentMethod() === false
        ) {
            if (!DoesOpenPaymentExist::validate($mollieOrder->payments()->getArrayCopy())) {
                $mollieOrder->createPayment([]);
            }

            if ($mollieOrder->getCheckoutUrl() !== null) {
                $redirectUrl = $mollieOrder->getCheckoutUrl();
            }

            $paymentPageFailEvent = new PaymentPageFailEvent(
                $context->getContext(),
                $order,
                $mollieOrder,
                $context->getSalesChannel()->getId(),
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

        $paymentPageRedirectEvent = new PaymentPageRedirectEvent(
            $context->getContext(),
            $order,
            $mollieOrder,
            $context->getSalesChannel()->getId(),
            $redirectUrl
        );

        $this->eventDispatcher->dispatch($paymentPageRedirectEvent, $paymentPageRedirectEvent::EVENT_NAME);

        return new RedirectResponse($redirectUrl);
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

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/payment/retry/{transactionId}/{redirectUrl}", defaults={"csrf_protected"=false},
     *                                                               name="frontend.mollie.payment.retry",
     *                                                               options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param SalesChannelContext $context
     * @param                     $transactionId
     *
     * @param                     $redirectUrl
     *
     * @return Response|RedirectResponse
     * @throws Exception
     */
    public function retry(SalesChannelContext $context, $transactionId, $redirectUrl): RedirectResponse
    {
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

    /**
     * Sets the API keys for Mollie based on the current context.
     *
     * @param SalesChannelContext $context
     *
     * @throws ApiException
     */
    private function setApiKeysBySalesChannelContext(SalesChannelContext $context): void
    {
        try {
            /** @var MollieSettingStruct $settings */
            $settings = $this->settingsService->getSettings($context->getSalesChannel()->getId());

            /** @var string $apiKey */
            $apiKey = $settings->isTestMode() === false ? $settings->getLiveApiKey() : $settings->getTestApiKey();

            // Log the used API keys
            if ($settings->isDebugMode()) {
                $this->logger->addEntry(
                    sprintf('Selected API key %s for sales channel %s (%s)', $apiKey, $context->getSalesChannel()->getName(), $settings->isTestMode() ? 'test-mode' : 'live-mode'),
                    $context->getContext(),
                    null,
                    [
                        'apiKey' => $apiKey,
                    ]
                );
            }

            // Set the API key
            $this->apiClient->setApiKey($apiKey);
        } catch (InconsistentCriteriaIdsException $e) {
            $this->logger->addEntry(
                $e->getMessage(),
                $context->getContext(),
                $e,
                [
                    'function' => 'set-mollie-api-key',
                ]
            );

            throw new RuntimeException(sprintf('Could not set Mollie Api Key, error: %s', $e->getMessage()));
        }
    }
}
