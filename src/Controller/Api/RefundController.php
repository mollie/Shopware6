<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api;

use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\RefundService;
use Mollie\Api\Resources\Refund;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class RefundController extends AbstractController
{
    /** @var OrderService */
    private $orderService;

    /** @var RefundService */
    private $refundService;

    /** @var LoggerInterface */
    private $logger;

    /**
     * Creates a new instance of the onboarding controller.
     *
     * @param LoggerInterface $logger
     * @param OrderService $orderService
     * @param RefundService $refundService
     */
    public function __construct(
        OrderService    $orderService,
        RefundService   $refundService,
        LoggerInterface $logger
    )
    {
        $this->orderService = $orderService;
        $this->refundService = $refundService;
        $this->logger = $logger;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/mollie/refund/order",
     *         defaults={"auth_enabled"=true}, name="api.mollie.refund.order", methods={"GET"})
     * @param QueryDataBag $query
     * @param Context $context
     * @return JsonResponse
     */
    public function refundOrder(QueryDataBag $query, Context $context): JsonResponse
    {
        try {
            $orderNumber = $query->get('number');

            if ($orderNumber === null) {
                throw new \InvalidArgumentException('Missing Argument for Order Number!');
            }

            $order = $this->orderService->getOrderByNumber($orderNumber, $context);



            $amount = (float)$query->get('amount', $order->getAmountTotal() - $this->refundService->getRefundedAmount($order, $context));

            $description = $query->get('description', sprintf("Refunded through Shopware API. Order number %s",
            $order->getOrderNumber()));

            $this->logger->info(sprintf('Refund for order %s with amount %s is triggered through the Shopware API.', $order->getOrderNumber(), $amount));

            $refund = $this->refundService->refund($order, $amount, $description, $context);
        } catch (ShopwareHttpException $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], 500);
        }

        return $this->json([
            'success' => ($refund instanceof Refund)
        ]);
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
     * @param string|null $description
     * @param Context $context
     * @return JsonResponse
     */
    private function refundResponse(string $orderId, float $amount, Context $context): JsonResponse
    {
        try {
            $order = $this->orderService->getOrder($orderId, $context);

            $this->logger->info(sprintf('Refund for order %s with amount %s is triggered through the Shopware administration.', $order->getOrderNumber(), $amount));

            $description = sprintf("Refunded through Shopware administration. Order number %s",
                $order->getOrderNumber());

            $refund = $this->refundService->refund($order, $amount, $description, $context);
        } catch (ShopwareHttpException $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], 500);
        }

        return $this->json([
            'success' => ($refund instanceof Refund)
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
            $order = $this->orderService->getOrder($orderId, $context);

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
            $order = $this->orderService->getOrder($orderId, $context);

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

            $order = $this->orderService->getOrder($orderId, $context);

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
}
