<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Route;

use Mollie\Shopware\Component\FlowBuilder\Event\Refund\RefundStartedEvent;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGateway;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGatewayInterface;
use Mollie\Shopware\Component\Refund\CreditNoteService;
use Mollie\Shopware\Component\Refund\Event\ModifyCreateRefundPayloadEvent;
use Mollie\Shopware\Component\Refund\RefundBuilder;
use Mollie\Shopware\Component\Refund\RefundBuilderInterface;
use Mollie\Shopware\Component\Refund\RefundCompositionBuilder;
use Mollie\Shopware\Component\Refund\RefundOrderLoader;
use Mollie\Shopware\Component\Refund\RefundPersister;
use Mollie\Shopware\Component\Refund\RefundTotalsBuilder;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
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
final class CreateRefundRoute extends AbstractCreateRefundRoute
{
    private const TYPE_FULL = 'FULL';
    private const TYPE_PARTIAL = 'PARTIAL';

    public function __construct(
        private readonly RefundOrderLoader $orderLoader,
        private readonly RefundCompositionBuilder $compositionBuilder,
        private readonly RefundTotalsBuilder $totalsBuilder,
        #[Autowire(service: RefundGateway::class)]
        private readonly RefundGatewayInterface $refundGateway,
        #[Autowire(service: RefundBuilder::class)]
        private readonly RefundBuilderInterface $refundBuilder,
        private readonly RefundPersister $refundPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        private readonly CreditNoteService $creditNoteService,
        #[Autowire(service: TransactionService::class)]
        private readonly TransactionServiceInterface $transactionService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractCreateRefundRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/api/_action/mollie/refund',
        name: 'api.action.mollie.refund',
        methods: ['POST'],
    )]
    public function create(Request $request, Context $context): JsonResponse
    {
        $orderId = (string) $request->get('orderId');

        $order = $this->orderLoader->load($orderId, $context);
        $payment = $this->orderLoader->extractPayment($order, $context);
        $orderNumber = (string) $order->getOrderNumber();
        $salesChannelId = (string) $order->getSalesChannelId();

        $requestAmount = $request->get('amount');
        $description = (string) $request->get('description', '');
        $internalDescription = (string) $request->get('internalDescription', '');
        $returnId = (string) $request->get('returnId', '');
        /** @var array<array{id: string, quantity: int, amount: float, resetStock: int}> $items */
        $items = $request->get('items', []);
        $items = array_values(array_filter($items, function ($item) {
            return (int) ($item['quantity'] ?? 0) > 0 || (float) ($item['amount'] ?? 0.0) > 0.0;
        }));
        $hasRequestedItems = count($items) > 0;
        $isFullRefund = ($requestAmount === null && ! $hasRequestedItems);
        $refundType = $isFullRefund ? self::TYPE_FULL : self::TYPE_PARTIAL;

        $this->logger->info('Refund create started', [
            'orderId' => $orderId,
            'orderNumber' => $orderNumber,
            'type' => $refundType,
            'requestAmount' => $requestAmount,
            'hasRequestedItems' => $hasRequestedItems,
            'salesChannelId' => $salesChannelId,
        ]);

        $refundedPerLine = [];
        $lineInfo = [];
        if ($hasRequestedItems) {
            $existingRefunds = $this->orderLoader->loadFresh($payment, $order)->getRefunds();
            $refundedPerLine = $this->compositionBuilder->buildRefundedAmounts($order, $existingRefunds);
            $lineInfo = $this->compositionBuilder->buildLineInfo($order);
        }

        $createRefund = $this->refundBuilder->build(
            $payment,
            $order,
            $items,
            $description,
            $requestAmount !== null ? (float) $requestAmount : null,
        );

        if ($returnId !== '') {
            $createRefund->setMetadata(['swagReturnId' => $returnId]);
        }

        $refundPayloadEvent = new ModifyCreateRefundPayloadEvent($createRefund, $order, $context);
        /** @var ModifyCreateRefundPayloadEvent $refundPayloadEvent */
        $refundPayloadEvent = $this->eventDispatcher->dispatch($refundPayloadEvent);
        $createRefund = $refundPayloadEvent->getCreateRefund();

        $refund = $this->refundGateway->createRefund($createRefund, $orderNumber, $salesChannelId);

        $stockItems = $hasRequestedItems ? $items : [];
        $dalRefund = $this->refundPersister->persist($order, $refund, $createRefund, $refundType, $description, $internalDescription, $stockItems, $refundedPerLine, $lineInfo, $context);

        $refundSettings = $this->settingsService->getRefundSettings($salesChannelId);
        if ($refundSettings->isCreateCreditNotes()) {
            $this->creditNoteService->addCreditNote($order, $refund, $refundSettings, $context);
        }

        $this->logger->info('Refund created successfully', [
            'orderId' => $order->getId(),
            'orderNumber' => $orderNumber,
            'mollieRefundId' => $refund->getId(),
            'amount' => $createRefund->getAmount()?->getValue(),
            'type' => $refundType,
        ]);

        $refundStartedEvent = new RefundStartedEvent($order, $dalRefund, $refund->getAmount()->getValue(), $context);
        $this->eventDispatcher->dispatch($refundStartedEvent);

        $refund->setRefundItems($dalRefund->getRefundItems());
        $refund->setInternalDescription((string) $dalRefund->getInternalDescription());

        // reload so the refund extension contains the just-persisted refund
        $order = $this->orderLoader->load($orderId, $context);

        // Mollie answered with the id of the refund. Record it on the payment and save it, so an
        // accounting export finds it in the custom fields of the order.
        $payment->addRefundId($refund->getId());
        $transactionId = $payment->getShopwareTransaction()->getId();
        $this->transactionService->savePaymentExtension($transactionId, $order, $payment, $context);

        $freshPayment = $this->orderLoader->loadFresh($payment, $order);
        $refunds = $freshPayment->getRefunds();
        $totals = $this->totalsBuilder->build($order, $payment, $freshPayment);

        return $this->json([
            'refund' => $refund,
            'totals' => $totals,
            'refundedItems' => $this->compositionBuilder->buildRefundedQuantities($order, $refunds),
            'refundedAmountItems' => $this->compositionBuilder->buildRefundedAmounts($order, $refunds),
        ]);
    }
}
