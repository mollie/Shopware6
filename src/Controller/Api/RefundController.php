<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api;

use Kiener\MolliePayments\Exception\CouldNotCancelMollieRefundException;
use Kiener\MolliePayments\Exception\CouldNotCreateMollieRefundException;
use Kiener\MolliePayments\Exception\CouldNotExtractMollieOrderIdException;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Exception\CouldNotFetchMollieRefundsException;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\RefundService;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class RefundController extends AbstractController
{
    /** @var LoggerInterface */
    private $logger;

    /** @var OrderService */
    private $orderService;

    /** @var RefundService */
    private $refundService;

    /**
     * Creates a new instance of the onboarding controller.
     *
     * @param LoggerInterface $logger
     * @param OrderService $orderService
     * @param RefundService $refundService
     */
    public function __construct(
        LoggerInterface $logger,
        OrderService    $orderService,
        RefundService   $refundService
    )
    {
        $this->logger = $logger;
        $this->orderService = $orderService;
        $this->refundService = $refundService;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/refund",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function refund(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->refundResponse($data->getAlnum('orderId'), $data->get('amount', 0.0), $context);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/refund",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function refundLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->refundResponse($data->getAlnum('orderId'), $data->get('amount', 0.0), $context);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/refund/cancel",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund.cancel", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function cancel(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->cancelResponse($data->getAlnum('orderId'), $data->get('refundId'), $context);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/refund/cancel",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund.cancel.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function cancelLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->cancelResponse($data->getAlnum('orderId'), $data->get('refundId'), $context);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/refund/list",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund.list", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function list(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->listResponse($data->getAlnum('orderId'), $context);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/refund/list",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund.list.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function listLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->listResponse($data->getAlnum('orderId'), $context);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/refund/total",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund.total", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function total(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->totalResponse($data->getAlnum('orderId'), $context);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/refund/total",
     *         defaults={"auth_enabled"=true}, name="api.action.mollie.refund.total.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function totalLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->totalResponse($data->getAlnum('orderId'), $context);
    }

    /**
     * @param string $orderId
     * @param float $amount
     * @param Context $context
     * @return JsonResponse
     */
    private function refundResponse(string $orderId, float $amount, Context $context): JsonResponse
    {
        try {
            $order = $this->getValidOrder($orderId, $context);

            $success = $this->refundService->refund($order, $amount, null, $context);
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
     * @param string $orderId
     * @param string|null $refundId
     * @param Context $context
     * @return JsonResponse
     */
    private function cancelResponse(string $orderId, ?string $refundId, Context $context): JsonResponse
    {
        try {
            $order = $this->getValidOrder($orderId, $context);

            $success = $this->refundService->cancel($order, $refundId, $context);
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
     * @param string $orderId
     * @param Context $context
     * @return JsonResponse
     */
    private function listResponse(string $orderId, Context $context): JsonResponse
    {
        try {
            $order = $this->getValidOrder($orderId, $context);

            $refunds = $this->refundService->getRefunds($order, $context);
        } catch (ShopwareHttpException $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (PaymentNotFoundException $e) {
            // This indicates there is no completed payment for this order, so there are no refunds yet.
            $refunds = [];
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], 500);
        }

        return $this->json($refunds ?? []);
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return JsonResponse
     */
    private function totalResponse(string $orderId, Context $context): JsonResponse
    {
        try {

            $order = $this->getValidOrder($orderId, $context);

            $remaining = $this->refundService->getRemainingAmount($order, $context);
            $refunded = $this->refundService->getRefundedAmount($order, $context);
            $voucherAmount = $this->refundService->getVoucherPaidAmount($order, $context);

        } catch (ShopwareHttpException $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (PaymentNotFoundException $e) {
            // This indicates there is no completed payment for this order, so there are no refunds yet.
            $remaining = 0;
            $refunded = 0;
            $voucherAmount = 0;
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], 500);
        }

        return $this->json(compact('remaining', 'refunded', 'voucherAmount'));
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return OrderEntity
     * @throws InvalidUuidException
     * @throws InvalidOrderException
     */
    private function getValidOrder(string $orderId, Context $context): OrderEntity
    {
        if (!Uuid::isValid($orderId)) {
            throw new InvalidUuidException($orderId);
        }

        $order = $this->orderService->getOrder($orderId, $context);

        if (!($order instanceof OrderEntity)) {
            throw new InvalidOrderException($orderId);
        }

        return $order;
    }
}
