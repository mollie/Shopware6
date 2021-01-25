<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Exception;
use Kiener\MolliePayments\Helper\DeliveryStateHelper;
use Kiener\MolliePayments\Helper\PaymentStatusHelper;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use RuntimeException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class WebhookController extends StorefrontController
{
    /** @var RouterInterface */
    private $router;

    /** @var EntityRepositoryInterface */
    private $orderTransactionRepository;

    /** @var MollieApiClient */
    private $apiClient;

    /** @var DeliveryStateHelper */
    private $deliveryStateHelper;

    /** @var PaymentStatusHelper */
    private $paymentStatusHelper;

    /** @var SettingsService */
    private $settingsService;

    /** @var LoggerService */
    private $logger;

    public function __construct(
        RouterInterface $router,
        EntityRepositoryInterface $orderTransactionRepository,
        MollieApiClient $apiClient,
        DeliveryStateHelper $deliveryStateHelper,
        PaymentStatusHelper $paymentStatusHelper,
        SettingsService $settingsService,
        LoggerService $logger
    )
    {
        $this->router = $router;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->apiClient = $apiClient;
        $this->deliveryStateHelper = $deliveryStateHelper;
        $this->paymentStatusHelper = $paymentStatusHelper;
        $this->settingsService = $settingsService;
        $this->logger = $logger;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/webhook/{transactionId}", defaults={"csrf_protected"=false}, name="frontend.mollie.webhook",
     *                                           options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @param SalesChannelContext $context
     * @param                     $transactionId
     *
     * @return JsonResponse
     */
    public function webhookCall(SalesChannelContext $context, $transactionId): JsonResponse
    {
        $criteria = null;
        $transaction = null;
        $order = null;
        $customFields = null;
        $mollieOrder = null;
        $mollieOrderId = null;
        $paymentStatus = null;
        $errorMessage = null;

        /** @var MollieSettingStruct $settings */
        $settings = $this->settingsService->getSettings(
            $context->getSalesChannel()->getId(),
            $context->getContext()
        );

        // Add a message to the log that the webhook has been triggered.
        if ($settings->isDebugMode()) {
            $this->logger->addEntry(
                sprintf('Webhook for transaction %s is triggered.', $transactionId),
                $context->getContext(),
                null,
                [
                    'transactionId' => $transactionId,
                ]
            );
        }

        /**
         * Create a search criteria to find the transaction by it's ID in the
         * transaction repository.
         *
         * @var $criteria
         */
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $transactionId));
            $criteria->addAssociation('order');
        } catch (InconsistentCriteriaIdsException $e) {
            $errorMessage = $errorMessage ?? $e->getMessage();
        }

        /**
         * Get the transaction from the order transaction repository. With the
         * transaction we can fetch the order from the database.
         *
         * @var OrderTransactionEntity $transaction
         */
        if ($criteria !== null) {
            try {
                $transaction = $this->orderTransactionRepository->search($criteria, $context->getContext())->first();
            } catch (Exception $e) {
                $errorMessage = $errorMessage ?? $e->getMessage();
            }
        }

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
            $customFields = $order->getCustomFields();
        } else {
            $errorMessage = $errorMessage ?? 'No order found for transaction with ID ' . $transactionId . '.';
        }

        /**
         * Set the API keys at Mollie based on the current context.
         */
        try {
            $this->setApiKeysBySalesChannelContext($context);
        } catch (Exception $e) {
            $this->logger->addEntry(
                $e->getMessage(),
                $context->getContext(),
                $e,
                [
                    'function' => 'webhook-set-api-keys'
                ]
            );
        }

        /**
         * With the order ID from the custom fields, we fetch the order from Mollie's
         * Orders API.
         *
         * @var $mollieOrder
         */
        if (is_array($customFields) && isset($customFields['mollie_payments']['order_id'])) {
            /** @var string $mollieOrderId */
            $mollieOrderId = $customFields['mollie_payments']['order_id'];

            /** @var Order $mollieOrder */
            try {
                $mollieOrder = $this->apiClient->orders->get($mollieOrderId, [
                    'embed' => 'payments'
                ]);
            } catch (ApiException $e) {
                $errorMessage = $errorMessage ?? $e->getMessage();
            }
        }

        /**
         * The payment status of the order is fetched from Mollie's Orders API. We
         * use this payment status to set the status in Shopware.
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

            // @todo Handle partial shipments better and make shipping status configurable
//            try {
//                $this->deliveryStateHelper->shipDelivery(
//                    $order,
//                    $mollieOrder,
//                    $context->getContext()
//                );
//            } catch (Exception $e) {
//                $errorMessage = $errorMessage ?? $e->getMessage();
//            }
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
                    'function' => 'webhook',
                ]
            );

            return new JsonResponse([
                'success' => false,
                'error' => $errorMessage
            ], 422);
        }

        /**
         * If no errors occurred during the webhook call, we return a success message.
         */
        return new JsonResponse([
            'success' => true
        ]);
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
                    sprintf('Selected API key %s for sales channel %s', $apiKey, $context->getSalesChannel()->getName()),
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
