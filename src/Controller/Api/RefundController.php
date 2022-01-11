<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api;

use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Facade\MollieRefundFacade;
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
    /** @var MollieRefundFacade */
    private $refundFacade;

    /** @var LoggerInterface */
    private $logger;

    /**
     * Creates a new instance of the onboarding controller.
     *
     * @param MollieRefundFacade $refundFacade
     * @param LoggerInterface $logger
     */
    public function __construct(
        MollieRefundFacade $refundFacade,
        LoggerInterface    $logger
    )
    {
        $this->refundFacade = $refundFacade;
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

            $amount = (float)$query->get('amount', 0);
            $description = $query->get('description', '');

            $refund = $this->refundFacade->refundUsingOrderNumber($orderNumber, $amount, $description, $context);
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
            $refund = $this->refundFacade->refundUsingOrderId($orderId, $amount, '', $context);
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
     * @param string $refundId
     * @param Context $context
     * @return JsonResponse
     */
    private function cancelResponse(string $orderId, string $refundId, Context $context): JsonResponse
    {
        try {
            $success = $this->refundFacade->cancelUsingOrderId($orderId, $refundId, $context);
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

            $refunds = $this->refundFacade->getRefundListUsingOrderId($orderId, $context);

            return $this->json($refunds);

        } catch (PaymentNotFoundException $e) {

            // This indicates there is no completed payment for this order,
            // so there are no refunds yet.
            return $this->json([]);

        } catch (ShopwareHttpException $e) {

            $this->logger->error($e->getMessage());

            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());

        } catch (\Throwable $e) {

            $this->logger->error($e->getMessage());

            return $this->json(['message' => $e->getMessage()], 500);
        }

    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return JsonResponse
     */
    private function totalResponse(string $orderId, Context $context): JsonResponse
    {
        try {
            $totals = $this->refundFacade->getRefundTotalsUsingOrderId($orderId, $context);
        } catch (ShopwareHttpException $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (PaymentNotFoundException $e) {
            // This indicates there is no completed payment for this order, so there are no refunds yet.
            $totals = [
                'remaining' => 0,
                'refunded' => 0,
                'voucherAmount' => 0,
            ];
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], 500);
        }

        return $this->json($totals);
    }
}
