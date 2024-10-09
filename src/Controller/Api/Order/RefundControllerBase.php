<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\Order;

use Kiener\MolliePayments\Components\RefundManager\RefundManagerInterface;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequestItem;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Refund\Exceptions\CreditNoteException;
use Kiener\MolliePayments\Service\Refund\RefundCreditNoteService;
use Kiener\MolliePayments\Service\Refund\RefundService;
use Kiener\MolliePayments\Traits\Api\ApiTrait;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

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
     * @var RefundCreditNoteService
     */
    private $creditNoteService;


    /**
     * @param OrderService $orderService
     * @param RefundManagerInterface $refundManager
     * @param RefundService $refundService
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderService $orderService,
        RefundManagerInterface $refundManager,
        RefundService $refundService,
        LoggerInterface $logger,
        RefundCreditNoteService $creditNoteService
    ) {
        $this->orderService = $orderService;
        $this->refundManager = $refundManager;
        $this->refundService = $refundService;
        $this->logger = $logger;
        $this->creditNoteService = $creditNoteService;
    }


    # ----------------------------------------------------------------------------------------------------------------------------------------
    # ----------------------------------------------------------------------------------------------------------------------------------------
    # ----------------------------------------------------------------------------------------------------------------------------------------
    # OPERATIONAL APIs

    /**
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

            $refundData = $this->refundManager->getData($order, $context);

            return $this->json($refundData->toArray());
        } catch (\Throwable $e) {
            $this->logger->error(
                $e->getMessage(),
                [
                    'error' => $e,
                ]
            );
            return $this->buildErrorResponse($e->getMessage());
        }
    }

    /**
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

        $response = $this->refundAction(
            $orderId,
            '',
            $description,
            $internalDescription,
            $amount,
            $items,
            $context
        );

        if ($response->getStatusCode() === 200 && $response->getContent() !== false && count($items) > 0) {
            $refundId = json_decode($response->getContent(), true)['refundId'];
            try {
                $this->creditNoteService->addCreditNoteToOrder($orderId, $refundId, $items, $context);
            } catch (CreditNoteException $exception) {
                if ($exception->getCode() === CreditNoteException::CODE_ADDING_CREDIT_NOTE_LINE_ITEMS) {
                    $this->logger->error($exception->getMessage(), ['code' => $exception->getCode(),]);
                    return $this->buildErrorResponse($exception->getMessage());
                }
                if ($exception->getCode() === CreditNoteException::CODE_WARNING_LEVEL) {
                    $this->logger->warning($exception->getMessage(), ['code' => $exception->getCode(),]);
                }
            }
        }

        return $response;
    }

    /**
     *
     * @param RequestDataBag $data
     * @param Context $context
     * @return JsonResponse
     */
    public function cancel(RequestDataBag $data, Context $context): JsonResponse
    {
        $orderId = $data->getAlnum('orderId');
        $refundId = $data->get('refundId');
        $response = $this->cancelRefundAction($orderId, $refundId, $context);

        if ($response->getStatusCode() === 200) {
            try {
                $this->creditNoteService->cancelCreditNoteToOrder($orderId, $refundId, $context);
            } catch (CreditNoteException $exception) {
                if ($exception->getCode() === CreditNoteException::CODE_REMOVING_CREDIT_NOTE_LINE_ITEMS) {
                    $this->logger->error($exception->getMessage(), ['code' => $exception->getCode(),]);
                    return $this->buildErrorResponse($exception->getMessage());
                }
                if ($exception->getCode() === CreditNoteException::CODE_WARNING_LEVEL) {
                    $this->logger->warning($exception->getMessage(), ['code' => $exception->getCode(),]);
                }
            }
        }

        return $response;
    }

    /**
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

            $refunds = $this->refundService->getRefunds($order, $context);

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
