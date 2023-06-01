<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Order;

use Kiener\MolliePayments\Components\RefundManager\RefundManagerInterface;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequestItem;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Refund\RefundService;
use Kiener\MolliePayments\Traits\Api\ApiTrait;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class RefundControllerBase extends AbstractController
{
    use ApiTrait;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var RefundManagerInterface
     */
    private $refundManager;

    /**
     * @var RefundService
     */
    private $refundService;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param OrderService $orderService
     * @param RefundManagerInterface $refundManager
     * @param RefundService $refundService
     * @param LoggerInterface $logger
     */
    public function __construct(OrderService $orderService, RefundManagerInterface $refundManager, RefundService $refundService, LoggerInterface $logger)
    {
        $this->orderService = $orderService;
        $this->refundManager = $refundManager;
        $this->refundService = $refundService;
        $this->logger = $logger;
    }


    # ----------------------------------------------------------------------------------------------------------------------------------------
    # ----------------------------------------------------------------------------------------------------------------------------------------
    # ----------------------------------------------------------------------------------------------------------------------------------------
    # OPERATIONAL APIs

    /**
     * @Route("/api/mollie/refund/order", name="api.mollie.refund.order", methods={"GET"})
     *
     * @param QueryDataBag $query
     * @param Context $context
     * @return JsonResponse
     */
    public function refundOrderNumber(QueryDataBag $query, Context $context): JsonResponse
    {
        $orderNumber = (string)$query->get('number');
        $description = $query->get('description', '');
        $internalDescription = $query->get('internalDescription', '');
        $amount = $query->get('amount', null); # we need NULL as full refund option
        # we don't allow items here
        # because this is a non-technical call, and
        # those items would (at the moment) require order line item IDs
        $items = [];

        # we have to convert to float ;)
        if ($amount !== null) {
            $amount = (float)$amount;
        }

        return $this->refundAction(
            '',
            $orderNumber,
            $description,
            $internalDescription,
            $amount,
            $items,
            $context
        );
    }

    # ----------------------------------------------------------------------------------------------------------------------------------------
    # ----------------------------------------------------------------------------------------------------------------------------------------
    # ----------------------------------------------------------------------------------------------------------------------------------------
    # TECHNICAL ADMIN APIs

    /**
     * @Route("/api/_action/mollie/refund-manager/data", name="api.action.mollie.refund-manager.data", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function refundManagerData(RequestDataBag $data, Context $context): JsonResponse
    {
        try {
            $orderId = $data->getAlnum('orderId');

            $order = $this->orderService->getOrder($orderId, $context);

            $data = $this->refundManager->getData($order, $context);

            return $this->json($data->toArray());
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return $this->buildErrorResponse($e->getMessage());
        }
    }

    /**
     * @Route("/api/_action/mollie/refund/list",name="api.action.mollie.refund.list", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function list(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->listRefundsAction($data->getAlnum('orderId'), $context);
    }

    /**
     * @Route("/api/_action/mollie/refund/total", name="api.action.mollie.refund.total", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function total(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->listTotalAction($data->getAlnum('orderId'), $context);
    }

    /**
     * @Route("/api/_action/mollie/refund", name="api.action.mollie.refund", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function refundOrderID(RequestDataBag $data, Context $context): JsonResponse
    {
        $orderId = $data->getAlnum('orderId', '');
        $description = $data->get('description', '');
        $internalDescription = $data->get('internalDescription', '');
        $amount = $data->get('amount', null);
        $items = [];

        $itemsBag = $data->get('items', []);

        if ($itemsBag instanceof RequestDataBag) {
            $items = $itemsBag->all();
        }

        return $this->refundAction(
            $orderId,
            '',
            $description,
            $internalDescription,
            $amount,
            $items,
            $context
        );
    }

    /**
     * @Route("/api/_action/mollie/refund/cancel", name="api.action.mollie.refund.cancel", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function cancel(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->cancelRefundAction($data->getAlnum('orderId'), $data->get('refundId'), $context);
    }

    /**
     * @Route("/api/v{version}/_action/mollie/refund-manager/data", name="api.action.mollie.refund-manager.data.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function refundManagerDataLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->refundManagerData($data, $context);
    }

    /**
     * @Route("/api/v{version}/_action/mollie/refund/list", name="api.action.mollie.refund.list.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function listLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->listRefundsAction($data->getAlnum('orderId'), $context);
    }

    /**
     * @Route("/api/v{version}/_action/mollie/refund/total", name="api.action.mollie.refund.total.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function totalLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->listTotalAction($data->getAlnum('orderId'), $context);
    }

    /**
     * @Route("/api/v{version}/_action/mollie/refund", name="api.action.mollie.refund.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function refundLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->refundOrderID($data, $context);
    }

    /**
     * @Route("/api/v{version}/_action/mollie/refund/cancel", name="api.action.mollie.refund.cancel.legacy", methods={"POST"})
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function cancelLegacy(RequestDataBag $data, Context $context): JsonResponse
    {
        return $this->cancelRefundAction($data->getAlnum('orderId'), $data->get('refundId'), $context);
    }

    # ----------------------------------------------------------------------------------------------------------------------------------------
    # ----------------------------------------------------------------------------------------------------------------------------------------
    # ----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param string $orderId
     * @param Context $context
     * @return JsonResponse
     */
    private function listRefundsAction(string $orderId, Context $context): JsonResponse
    {
        try {
            $order = $this->orderService->getOrder($orderId, $context);

            $refunds = $this->refundService->getRefunds($order);

            return $this->json($refunds);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return $this->buildErrorResponse($e->getMessage());
        }
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return JsonResponse
     */
    private function listTotalAction(string $orderId, Context $context): JsonResponse
    {
        try {
            $order = $this->orderService->getOrder($orderId, $context);

            $data = $this->refundManager->getData($order, $context);

            $json = [
                'remaining' => round($data->getAmountRemaining(), 2),
                'refunded' => round($data->getAmountCompletedRefunds(), 2),
                'voucherAmount' => round($data->getAmountVouchers(), 2),
                'pendingRefunds' => round($data->getAmountPendingRefunds(), 2),
            ];

            return $this->json($json);
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
            return $this->buildErrorResponse($e->getMessage());
        }
    }

    /**
     * @param string $orderId
     * @param string $orderNumber
     * @param string $description
     * @param string $internalDescription
     * @param null|float $amount
     * @param array<mixed> $items
     * @param Context $context
     * @return JsonResponse
     */
    private function refundAction(string $orderId, string $orderNumber, string $description, string $internalDescription, ?float $amount, array $items, Context $context): JsonResponse
    {
        try {
            if (!empty($orderId)) {
                $order = $this->orderService->getOrder($orderId, $context);
            } else {
                if (empty($orderNumber)) {
                    throw new \InvalidArgumentException('Missing Argument for Order ID or order number!');
                }

                $order = $this->orderService->getOrderByNumber($orderNumber, $context);
            }


            $refundRequest = new RefundRequest(
                (string)$order->getOrderNumber(),
                $description,
                $internalDescription,
                $amount
            );

            foreach ($items as $item) {
                $refundRequest->addItem(new RefundRequestItem(
                    (string)$item['id'],
                    $item['amount'],
                    (int)$item['quantity'],
                    (int)$item['resetStock']
                ));
            }

            $refund = $this->refundManager->refund(
                $order,
                $refundRequest,
                $context
            );

            return $this->json([
                'success' => true,
                'refundId' => $refund->id
            ]);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());

            return $this->buildErrorResponse($e->getMessage());
        }
    }

    /**
     * @param string $orderId
     * @param string $refundId
     * @param Context $context
     * @return JsonResponse
     */
    private function cancelRefundAction(string $orderId, string $refundId, Context $context): JsonResponse
    {
        try {
            $success = $this->refundManager->cancelRefund($orderId, $refundId, $context);
            return $this->json([
                'success' => $success
            ]);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return $this->buildErrorResponse($e->getMessage());
        }
    }
}
