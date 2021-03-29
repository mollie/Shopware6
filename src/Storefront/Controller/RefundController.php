<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Exception;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\RefundService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class RefundController extends StorefrontController
{
    private const RESPONSE_KEY_REFUNDABLE = 'refundable';
    private const RESPONSE_KEY_REFUNDED = 'refunded';

    /** @var MollieApiFactory */
    private $apiFactory;

    /** @var EntityRepositoryInterface */
    private $orderLineItemRepository;

    /** @var OrderService */
    private $orderService;

    /** @var OrderTransactionStateHandler */
    private $orderTransactionStateHandler;

    /** @var SettingsService */
    private $settingsService;

    /** @var RefundService  */
    private $refundService;

    /**
     * Creates a new instance of the onboarding controller.
     *
     * @param MollieApiFactory $apiFactory
     * @param EntityRepositoryInterface $orderLineItemRepository
     * @param OrderService $orderService
     * @param OrderTransactionStateHandler $orderTransactionStateHandler
     * @param SettingsService $settingsService
     * @param RefundService $refundService
     */
    public function __construct(
        MollieApiFactory $apiFactory,
        EntityRepositoryInterface $orderLineItemRepository,
        OrderService $orderService,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        SettingsService $settingsService,
        RefundService $refundService
    )
    {
        $this->apiFactory = $apiFactory;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->orderService = $orderService;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->settingsService = $settingsService;
        $this->refundService = $refundService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/refund",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function refund(RequestDataBag $data, Context $context): JsonResponse
    {
        $orderId = $data->getAlnum('orderId');

        if (!Uuid::isValid($orderId)) {
            return $this->json([
                'error' => 'invalid id'
            ], 400);
        }

        $order = $this->orderService->getOrder($orderId, $context);

        if (is_null($order)) {
            return $this->json([
                'error' => 'order not found'
            ], 404);
        }

        $amount = $data->get('amount');

        try {
            $success = $this->refundService->refund($order, $amount);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        return $this->json([
            'success' => $success
        ]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/refund/total",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund.total", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function total(RequestDataBag $data, Context $context): JsonResponse
    {
        $orderId = $data->getAlnum('orderId');

        if (!Uuid::isValid($orderId)) {
            return $this->json([
                'error' => 'invalid id'
            ], 400);
        }

        $order = $this->orderService->getOrder($orderId, $context);

        if (is_null($order)) {
            return $this->json([
                'error' => 'order not found'
            ], 404);
        }

        $refundable = $this->refundService->getRefundableAmount($order);
        $refunded = $this->refundService->getRefundedAmount($order);

        return $this->json([
            self::RESPONSE_KEY_REFUNDABLE => $refundable,
            self::RESPONSE_KEY_REFUNDED => $refunded,
        ]);
    }
}
