<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Controller;

use Mollie\Shopware\Component\Mollie\Gateway\RefundGateway;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\RefundCollection as MollieRefundCollection;
use Mollie\Shopware\Component\Refund\CreditNoteService;
use Mollie\Shopware\Component\Refund\DAL\Order\OrderExtension;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundCollection;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Mollie\Shopware\Component\Refund\Event\ModifyCreateRefundPayloadEvent;
use Mollie\Shopware\Component\Refund\RefundBuilder;
use Mollie\Shopware\Component\Refund\RefundBuilderInterface;
use Mollie\Shopware\Component\Refund\RefundPersister;
use Mollie\Shopware\Component\Refund\Struct\CartStruct;
use Mollie\Shopware\Component\Refund\Struct\RefundOverviewStruct;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api']])]
final class RefundController extends AbstractController
{
    private const TYPE_FULL = 'FULL';
    private const TYPE_PARTIAL = 'PARTIAL';

    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        #[Autowire(service: RefundGateway::class)]
        private readonly RefundGatewayInterface $refundGateway,
        #[Autowire(service: RefundBuilder::class)]
        private readonly RefundBuilderInterface $refundBuilder,
        private readonly RefundPersister $refundPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: SettingsService::class)]
        private readonly AbstractSettingsService $settingsService,
        private readonly CreditNoteService $creditNoteService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/api/_action/mollie/order/refund-overview',
        name: 'api.action.mollie.order.refund-overview',
        methods: ['POST'],
    )]
    public function overview(Request $request, Context $context): JsonResponse
    {
        $orderId = (string) $request->get('orderId');

        $order = $this->loadOrder($orderId, $context);
        $orderNumber = (string) $order->getOrderNumber();

        $this->logger->info('Refund overview requested', [
            'orderId' => $orderId,
            'orderNumber' => $orderNumber,
        ]);

        $struct = new RefundOverviewStruct();
        $struct->setTaxStatus((string) $order->getTaxStatus());

        $mollieExtension = $order->getTransactions()?->first()?->getExtension(Mollie::EXTENSION);

        if (! $mollieExtension instanceof Payment) {
            $this->logger->debug('No Mollie payment found for refund overview', [
                'orderId' => $orderId,
                'orderNumber' => $orderNumber,
            ]);

            return $this->json($struct);
        }

        $payment = $mollieExtension;

        $struct->setCart(CartStruct::fromOrder($order));

        $refunds = $this->refundGateway->listRefunds($payment->getId(), $orderNumber, $order->getSalesChannelId());
        $refunds = $this->enrichRefundsWithComposition($refunds, $order);

        $amountRefunded = $refunds->getSumRefunded();
        $amountPending = $refunds->getSumPending();
        $remaining = max(0.0, $order->getAmountTotal() - $amountRefunded - $amountPending);

        $totals = $struct->getTotals();
        $totals->setRefunded($amountRefunded);
        $totals->setPendingRefunds($amountPending);
        $totals->setRemaining($remaining);
        $struct->setRefunds($refunds);

        return $this->json($struct);
    }

    #[Route(
        path: '/api/_action/mollie/refund',
        name: 'api.action.mollie.refund',
        methods: ['POST'],
    )]
    public function create(Request $request, Context $context): JsonResponse
    {
        $orderId = (string) $request->get('orderId');

        $order = $this->loadOrder($orderId, $context);
        $payment = $this->extractMolliePayment($order);
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
        $dalRefund = $this->refundPersister->persist($order, $refund, $createRefund, $refundType, $description, $internalDescription, $stockItems, $context);

        $refundSettings = $this->settingsService->getRefundSettings($salesChannelId);
        if ($refundSettings->isCreateCreditNotes()) {
            $this->creditNoteService->addCreditNote($order->getId(), $refund, $refundSettings, $context);
        }

        $this->logger->info('Refund created successfully', [
            'orderId' => $order->getId(),
            'orderNumber' => $orderNumber,
            'mollieRefundId' => $refund->getId(),
            'amount' => $createRefund->getAmount()?->getValue(),
            'type' => $refundType,
        ]);

        $refund->setRefundItems($dalRefund->getRefundItems());
        $refund->setInternalDescription((string) $dalRefund->getInternalDescription());

        return $this->json($refund);
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

        $order = $this->loadOrder($orderId, $context);
        $payment = $this->extractMolliePayment($order);
        $orderNumber = (string) $order->getOrderNumber();

        $this->logger->info('Refund cancel requested', [
            'orderId' => $orderId,
            'orderNumber' => $orderNumber,
            'refundId' => $refundId,
        ]);

        $this->refundGateway->cancelRefund($payment->getId(), $refundId, $orderNumber, (string) $order->getSalesChannelId());

        $this->creditNoteService->cancelCreditNote($orderId, $refundId, $context);

        return $this->json(['success' => true]);
    }

    private function enrichRefundsWithComposition(MollieRefundCollection $mollieRefunds, OrderEntity $order): MollieRefundCollection
    {
        $dalRefunds = $order->getExtension(OrderExtension::REFUND_PROPERTY_NAME);

        if (! $dalRefunds instanceof RefundCollection) {
            return $mollieRefunds;
        }
        /** @var RefundEntity $dalRefund */
        foreach ($dalRefunds as $dalRefund) {
            $mollieRefundId = (string) $dalRefund->getMollieRefundId();
            $mollieRefund = $mollieRefunds->findByMollieId($mollieRefundId);

            if ($mollieRefund === null) {
                continue;
            }

            $mollieRefund->setRefundItems($dalRefund->getRefundItems());
            $mollieRefund->setInternalDescription((string) $dalRefund->getInternalDescription());
        }

        return $mollieRefunds;
    }

    private function loadOrder(string $orderId, Context $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems.product');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('currency');
        $criteria->addAssociation(OrderExtension::REFUND_PROPERTY_NAME . '.refundItems');
        $criteria->getAssociation('transactions')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
            ->setLimit(1)
        ;

        /** @var null|OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if (! $order instanceof OrderEntity) {
            throw new \RuntimeException(sprintf('Order "%s" not found', $orderId));
        }

        return $order;
    }

    private function extractMolliePayment(OrderEntity $order): Payment
    {
        $transaction = $order->getTransactions()?->first();

        if ($transaction === null) {
            throw new \RuntimeException(sprintf('No Mollie transaction found for order "%s"', $order->getId()));
        }

        $payment = $transaction->getExtension(Mollie::EXTENSION);

        if (! $payment instanceof Payment) {
            throw new \RuntimeException(sprintf('No Mollie payment extension found for order "%s"', $order->getId()));
        }

        return $payment;
    }
}
