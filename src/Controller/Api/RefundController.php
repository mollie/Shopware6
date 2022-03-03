<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api;

use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Facade\MollieRefundFacade;
use Kiener\MolliePayments\Service\OrderService;
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

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var MollieRefundFacade
     */
    private $refunds;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param MollieRefundFacade $refundFacade
     * @param OrderService $orderService
     * @param LoggerInterface $logger
     */
    public function __construct(MollieRefundFacade $refundFacade, OrderService $orderService, LoggerInterface $logger)
    {
        $this->refunds = $refundFacade;
        $this->orderService = $orderService;
        $this->logger = $logger;
    }


    # ----------------------------------------------------------------------------------------------------------------------------------------
    # ----------------------------------------------------------------------------------------------------------------------------------------
    # ----------------------------------------------------------------------------------------------------------------------------------------
    # PUBLIC EASY APIs

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/mollie/refund/order", defaults={"auth_enabled"=true}, name="api.mollie.refund.order", methods={"GET"})
     *
     * @param QueryDataBag $query
     * @param Context $context
     * @return JsonResponse
     */
    public function refundOrder(QueryDataBag $query, Context $context): JsonResponse
    {
        try {

            $orderNumber = (string)$query->get('number');

            if (empty($orderNumber)) {
                throw new \InvalidArgumentException('Missing Argument for Order Number!');
            }

            $amount = $query->get('amount', null);
            $description = $query->get('description', '');

            $order = $this->orderService->getOrderByNumber($orderNumber, $context);

            $refund = $this->refunds->startRefundProcess(
                $order,
                $description,
                $amount,
                [],
                $context
            );

            return $this->json([
                'success' => true
            ]);

        } catch (ShopwareHttpException $e) {

            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());

        } catch (\Throwable $e) {

            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], 500);
        }
    }


    # ----------------------------------------------------------------------------------------------------------------------------------------
    # ----------------------------------------------------------------------------------------------------------------------------------------
    # ----------------------------------------------------------------------------------------------------------------------------------------
    # TECHNICAL ADMIN APIs

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/refund/list", defaults={"auth_enabled"=true}, name="api.action.mollie.refund.list", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function list(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->listMollieData($data->getAlnum('orderId'), $context);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/refund/total", defaults={"auth_enabled"=true}, name="api.action.mollie.refund.total", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function total(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->listTotalValues($data->getAlnum('orderId'), $context);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/refund", defaults={"auth_enabled"=true}, name="api.action.mollie.refund", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function refund(RequestDataBag $data, Context $context): JsonResponse
    {
        $orderId = $data->getAlnum('orderId', '');
        $description = $data->get('description', '');
        $amount = $data->get('amount', 0.0);

        /** @var RequestDataBag $items */
        $items = $data->get('items', []);

        $refundItems = [];
        if ($items instanceof RequestDataBag) {
            $refundItems = $items->all();
        }
        return $this->startRefund(
            $orderId,
            $description,
            $amount,
            $refundItems,
            $context
        );
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/mollie/refund/cancel", defaults={"auth_enabled"=true}, name="api.action.mollie.refund.cancel", methods={"POST"})
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
     * @Route("/api/v{version}/_action/mollie/refund/list", defaults={"auth_enabled"=true}, name="api.action.mollie.refund.list.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function listLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->listMollieData($data->getAlnum('orderId'), $context);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/refund/total", defaults={"auth_enabled"=true}, name="api.action.mollie.refund.total.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function totalLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->listTotalValues($data->getAlnum('orderId'), $context);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/refund", defaults={"auth_enabled"=true}, name="api.action.mollie.refund.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function refundLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->startRefund(
            $data->getAlnum('orderId'),
            $data->get('description', ''),
            $data->get('amount', 0.0),
            $data->get('items', []),
            $context
        );
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/mollie/refund/cancel", defaults={"auth_enabled"=true}, name="api.action.mollie.refund.cancel.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function cancelLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->cancelResponse($data->getAlnum('orderId'), $data->get('refundId'), $context);
    }

    # ----------------------------------------------------------------------------------------------------------------------------------------
    # ----------------------------------------------------------------------------------------------------------------------------------------
    # ----------------------------------------------------------------------------------------------------------------------------------------


    /**
     * @param string $orderId
     * @param Context $context
     * @return JsonResponse
     */
    private function listMollieData(string $orderId, Context $context): JsonResponse
    {
        try {

            $refunds = $this->refunds->getMollieRefundData($orderId, $context);
            return $this->json($refunds);

        } catch (PaymentNotFoundException $e) {

            # This indicates there is no completed payment for this order,
            # so there are no refunds yet.
            $totals = [
                'refunds' => [],
                'totals' => [
                    'remaining' => 0,
                    'voucherAmount' => 0,
                    'pendingRefunds' => 0,
                    'refunded' => 0,
                ],
            ];

            return $this->json($totals);

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
    private function listTotalValues(string $orderId, Context $context): JsonResponse
    {
        try {

            $totals = $this->refunds->getTotalsByOrderId($orderId, $context);
            return $this->json($totals);

        } catch (ShopwareHttpException $e) {

            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], $e->getStatusCode());

        } catch (PaymentNotFoundException $e) {

            # This indicates there is no completed payment for this order, so there are no refunds yet.
            $totals = [
                'remaining' => 0,
                'refunded' => 0,
                'voucherAmount' => 0,
                'pendingRefunds' => 0,
            ];

            return $this->json($totals);

        } catch (\Throwable $e) {

            $this->logger->error($e->getMessage());
            return $this->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param string $orderId
     * @param string $description
     * @param float $amount
     * @param array $items
     * @param Context $context
     * @return JsonResponse
     */
    private function startRefund(string $orderId, string $description, float $amount, array $items, Context $context): JsonResponse
    {
        try {

            $order = $this->orderService->getOrder($orderId, $context);

            $refund = $this->refunds->startRefundProcess(
                $order,
                $description,
                $amount,
                $items,
                $context
            );

            return $this->json([
                'success' => ($refund instanceof Refund)
            ]);

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
     * @param string $refundId
     * @param Context $context
     * @return JsonResponse
     */
    private function cancelResponse(string $orderId, string $refundId, Context $context): JsonResponse
    {
        try {
            $success = $this->refunds->cancelUsingOrderId($orderId, $refundId, $context);
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

}
