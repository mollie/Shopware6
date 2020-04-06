<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Exception;
use Kiener\MolliePayments\Helper\PaymentStatusHelper;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use RuntimeException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class WebhookController extends StorefrontController
{
    /** @var RouterInterface */
    protected $router;

    /** @var EntityRepository */
    protected $orderTransactionRepository;

    /** @var MollieApiClient */
    protected $apiClient;

    /** @var PaymentStatusHelper */
    protected $paymentStatusHelper;

    /** @var SettingsService */
    protected $settingsService;

    public function __construct(
        RouterInterface $router,
        EntityRepository $orderTransactionRepository,
        MollieApiClient $apiClient,
        PaymentStatusHelper $paymentStatusHelper,
        SettingsService $settingsService
    )
    {
        $this->router = $router;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->apiClient = $apiClient;
        $this->paymentStatusHelper = $paymentStatusHelper;
        $this->settingsService = $settingsService;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/webhook/{transactionId}", defaults={"csrf_protected"=false}, name="frontend.mollie.webhook",
     *                                           options={"seo"="false"}, methods={"GET", "POST"})
     *
     * @return Response
     * @throws ApiException
     */
    public function webhookCall(SalesChannelContext $context, $transactionId) : Response
    {
        $criteria = null;
        $transaction = null;
        $order = null;
        $customFields = null;
        $mollieOrder = null;
        $mollieOrderId = null;
        $paymentStatus = null;
        $errorMessage = null;

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
        $this->setApiKeysBySalesChannelContext($context);

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
            return $this->returnJson([
                'success' => false,
                'error' => $errorMessage
            ]);
        }

        /**
         * If no errors occurred during the webhook call, we return a success message.
         */
        return $this->returnJson([
            'success' => true
        ]);
    }

    /**
     * Returns the URL of the webhook controller.
     *
     * @param $transactionId
     * @return string
     */
    public function getWebhookUrl($transactionId) : string
    {
        return $this->router->generate('frontend.mollie.webhook', [
            'transactionId' => $transactionId
        ]);
    }

    /**
     * Returns a JSON response.
     *
     * @param array $data
     * @return Response
     */
    protected function returnJson(array $data) : Response
    {
        return new Response(json_encode($data), 200, [
            'Content-Type' => 'application/json'
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
            $mollieSettings = $this->settingsService->getSettings($context->getSalesChannel()->getId());

            $this->apiClient->setApiKey(
                strtolower($_ENV['APP_ENV']) === 'prod' && !$mollieSettings->isTestMode() ? $mollieSettings->getLiveApiKey() : $mollieSettings->getTestApiKey()
            );
        } catch (InconsistentCriteriaIdsException $e) {
            throw new RuntimeException('Could not set Mollie Api Key' . $e->getMessage());
        }
    }
}