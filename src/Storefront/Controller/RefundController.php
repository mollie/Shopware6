<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\RefundService;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class RefundController extends StorefrontController
{
    private const RESPONSE_KEY_REFUNDABLE = 'refundable';
    private const RESPONSE_KEY_REFUNDED = 'refunded';

    /** @var MollieApiFactory */
    private $apiFactory;

    /** @var LoggerInterface */
    private $logger;

    /** @var EntityRepositoryInterface */
    private $orderLineItemRepository;

    /** @var OrderService */
    private $orderService;

    /** @var OrderTransactionStateHandler */
    private $orderTransactionStateHandler;

    /** @var SettingsService */
    private $settingsService;

    /** @var RefundService */
    private $refundService;

    /**
     * Creates a new instance of the onboarding controller.
     *
     * @param MollieApiFactory $apiFactory
     * @param LoggerInterface $logger
     * @param EntityRepositoryInterface $orderLineItemRepository
     * @param OrderService $orderService
     * @param OrderTransactionStateHandler $orderTransactionStateHandler
     * @param SettingsService $settingsService
     * @param RefundService $refundService
     */
    public function __construct(
        MollieApiFactory $apiFactory,
        LoggerInterface $logger,
        EntityRepositoryInterface $orderLineItemRepository,
        OrderService $orderService,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        SettingsService $settingsService,
        RefundService $refundService
    )
    {
        $this->apiFactory = $apiFactory;
        $this->logger = $logger;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->orderService = $orderService;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->settingsService = $settingsService;
        $this->refundService = $refundService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/refund",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund", methods={"POST"})
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
        $amount = $data->get('amount');

        try {
            $order = $this->getValidOrder($orderId, $context);

            $success = $this->refundService->refund($order, $amount);
        } catch (ShopwareHttpException $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], 500);
        }

        return $this->json([
            'success' => $success
        ]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/refund/cancel",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund.cancel", methods={"POST"})
     * @Route("/api/v{version}/_action/mollie/refund/cancel",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund.cancel", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function cancel(RequestDataBag $data, Context $context): JsonResponse
    {
        $orderId = $data->getAlnum('orderId');
        $refundId = $data->get('refundId');

        try {
            $order = $this->getValidOrder($orderId, $context);

            $success = $this->refundService->cancel($order, $refundId);
        } catch (ShopwareHttpException $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], 500);
        }

        return $this->json([
            'success' => $success
        ]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/refund/list",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund.list", methods={"POST"})
     * @Route("/api/v{version}/_action/mollie/refund/list",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund.list", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function list(RequestDataBag $data, Context $context): JsonResponse
    {
        $orderId = $data->getAlnum('orderId');

        try {
            $order = $this->getValidOrder($orderId, $context);

            $refunds = $this->refundService->getRefunds($order);
        } catch (ShopwareHttpException $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], 500);
        }

        return $this->json($refunds ?? []);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/refund/total",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund.total", methods={"POST"})
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

        try {
            $order = $this->getValidOrder($orderId, $context);

            $this->refundService->getRefunds($order);

            $refundable = $this->refundService->getRefundableAmount($order);
            $refunded = $this->refundService->getRefundedAmount($order);
        } catch (ShopwareHttpException $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['error' => $e->getMessage()], 404);
        }

        return $this->json([
            self::RESPONSE_KEY_REFUNDABLE => $refundable,
            self::RESPONSE_KEY_REFUNDED => $refunded,
        ]);
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return OrderEntity
     */
    private function getValidOrder(string $orderId, Context $context): OrderEntity
    {
        if (!Uuid::isValid($orderId)) {
            throw new InvalidUuidException($orderId);
        }

        $order = $this->orderService->getOrder($orderId, $context);

        if (is_null($order)) {
            throw new InvalidOrderException($orderId);
        }

        return $order;
    }
}
