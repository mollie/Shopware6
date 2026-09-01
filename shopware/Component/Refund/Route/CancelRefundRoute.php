<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Route;

use Mollie\Shopware\Component\Mollie\Gateway\RefundGateway;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGatewayInterface;
use Mollie\Shopware\Component\Refund\CreditNoteService;
use Mollie\Shopware\Component\Refund\RefundCompositionBuilder;
use Mollie\Shopware\Component\Refund\RefundOrderLoader;
use Mollie\Shopware\Component\Refund\RefundTotalsBuilder;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
final class CancelRefundRoute extends AbstractCancelRefundRoute
{
    public function __construct(
        private readonly RefundOrderLoader $orderLoader,
        private readonly RefundCompositionBuilder $compositionBuilder,
        private readonly RefundTotalsBuilder $totalsBuilder,
        #[Autowire(service: RefundGateway::class)]
        private readonly RefundGatewayInterface $refundGateway,
        private readonly CreditNoteService $creditNoteService,
        #[Autowire(service: TransactionService::class)]
        private readonly TransactionServiceInterface $transactionService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractCancelRefundRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/api/_action/mollie/refund/cancel',
        name: 'api.action.mollie.refund.cancel',
        methods: ['POST'],
    )]
    public function cancel(Request $request, Context $context): JsonResponse
    {
        $orderId = (string) $request->get('orderId');
        $refundId = (string) $request->get('refundId');

        $order = $this->orderLoader->load($orderId, $context);
        $payment = $this->orderLoader->extractPayment($order, $context);
        $orderNumber = (string) $order->getOrderNumber();

        $this->logger->info('Refund cancel requested', [
            'orderId' => $orderId,
            'orderNumber' => $orderNumber,
            'refundId' => $refundId,
        ]);

        $this->refundGateway->cancelRefund($payment->getId(), $refundId, $orderNumber, (string) $order->getSalesChannelId());

        $this->creditNoteService->cancelCreditNote($orderId, $refundId, $context);

        // The refund is gone at Mollie, so its id must not stay in the export data of the order.
        $payment->removeRefundId($refundId);
        $transactionId = $payment->getShopwareTransaction()->getId();
        $this->transactionService->savePaymentExtension($transactionId, $order, $payment, $context);

        $freshPayment = $this->orderLoader->loadFresh($payment, $order);
        $refunds = $freshPayment->getRefunds();
        $totals = $this->totalsBuilder->build($order, $payment, $freshPayment);

        return $this->json([
            'success' => true,
            'totals' => $totals,
            'refundedItems' => $this->compositionBuilder->buildRefundedQuantities($order, $refunds),
            'refundedAmountItems' => $this->compositionBuilder->buildRefundedAmounts($order, $refunds),
        ]);
    }
}
