<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Controller;

use Mollie\Shopware\Component\Mollie\CreateRefund;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGateway;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGatewayInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Refund\Struct\CartStruct;
use Mollie\Shopware\Component\Refund\Struct\RefundOverviewStruct;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
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
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        #[Autowire(service: RefundGateway::class)]
        private readonly RefundGatewayInterface $refundGateway,
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

        $struct = new RefundOverviewStruct();
        $struct->setTaxStatus($order->getTaxStatus());

        $mollieExtension = $order->getTransactions()?->first()?->getExtension(Mollie::EXTENSION);

        if (! $mollieExtension instanceof Payment) {
            return $this->json($struct);
        }

        $payment = $mollieExtension;

        $struct->setCart(CartStruct::fromOrder($order));

        $refunds = $this->refundGateway->listRefunds($payment->getId(), $order->getSalesChannelId());

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

        $currency = (string) $order->getCurrency()?->getIsoCode();
        $salesChannelId = (string) $order->getSalesChannelId();

        $amount = $request->get('amount');

        /** @var array<array{id: string, quantity: int, amount: float}> $items */
        $items = array_values(array_filter($request->get('items', []), function ($item) {
            return (int) ($item['quantity'] ?? 0) > 0 || (float) ($item['amount'] ?? 0.0) > 0.0;
        }));

        if ($amount === null && count($items) === 0) {
            $refunds = $this->refundGateway->listRefunds($payment->getId(), $salesChannelId);
            $amount = max(0.0, $order->getAmountTotal() - $refunds->getSumRefunded() - $refunds->getSumPending());
        }

        if (count($items) > 0) {
            $amount = $this->calculateAmountFromItems($items, $order->getLineItems() ?? new OrderLineItemCollection(), $orderId);
        }

        $amount = (float) $amount;

        $createRefund = new CreateRefund(
            $payment->getId(),
            new Money($amount, $currency),
            (string) $request->get('description', ''),
        );

        $refund = $this->refundGateway->createRefund($createRefund, $salesChannelId);

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

        $this->refundGateway->cancelRefund($payment->getId(), $refundId, (string) $order->getSalesChannelId());

        return $this->json(['success' => true]);
    }

    /**
     * @param array<array{id: string, quantity: int, amount: float}> $items
     */
    private function calculateAmountFromItems(array $items, OrderLineItemCollection $lineItems, string $orderId): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            $itemAmount = (float) ($item['amount'] ?? 0.0);

            if ($itemAmount > 0.0) {
                $total += $itemAmount;
                continue;
            }

            $lineItemId = (string) ($item['id'] ?? '');
            $lineItem = $lineItems->get($lineItemId);

            if (! $lineItem instanceof OrderLineItemEntity) {
                throw new \RuntimeException(sprintf('Line item "%s" not found in order "%s"', $lineItemId, $orderId));
            }

            $total += $lineItem->getUnitPrice() * $quantity;
        }

        return round($total, 2);
    }

    private function loadOrder(string $orderId, Context $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('currency');
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
