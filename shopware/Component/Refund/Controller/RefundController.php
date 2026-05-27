<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Controller;

use Mollie\Shopware\Component\Mollie\CreateRefund;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGateway;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGatewayInterface;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\RefundCollection as MollieRefundCollection;
use Mollie\Shopware\Component\Refund\DAL\Order\OrderExtension;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundCollection;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Mollie\Shopware\Component\Refund\Struct\CartStruct;
use Mollie\Shopware\Component\Refund\Struct\RefundOverviewStruct;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockAlteration;
use Shopware\Core\Content\Product\Stock\StockStorage;
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
     * @param EntityRepository<RefundCollection> $refundRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        #[Autowire(service: RefundGateway::class)]
        private readonly RefundGatewayInterface $refundGateway,
        #[Autowire(service: 'mollie_refund.repository')]
        private readonly EntityRepository $refundRepository,
        #[Autowire(service: StockStorage::class)]
        private readonly AbstractStockStorage $stockStorage,
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
        $struct->setTaxStatus((string) $order->getTaxStatus());

        $mollieExtension = $order->getTransactions()?->first()?->getExtension(Mollie::EXTENSION);

        if (! $mollieExtension instanceof Payment) {
            return $this->json($struct);
        }

        $payment = $mollieExtension;

        $struct->setCart(CartStruct::fromOrder($order));

        $refunds = $this->refundGateway->listRefunds($payment->getId(), $order->getSalesChannelId());
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

        $currency = (string) $order->getCurrency()?->getIsoCode();
        $salesChannelId = (string) $order->getSalesChannelId();

        $requestAmount = $request->get('amount');
        $description = (string) $request->get('description', '');
        $internalDescription = (string) $request->get('internalDescription', '');
        /** @var array<array{id: string, quantity: int, amount: float,resetStock:int}> $items */
        $items = $request->get('items', []);
        $items = array_values(array_filter($items, function ($item) {
            return (int) ($item['quantity'] ?? 0) > 0 || (float) ($item['amount'] ?? 0.0) > 0.0;
        }));

        $orderLineItems = $order->getLineItems() ?? new OrderLineItemCollection();
        $orderDeliveries = $order->getDeliveries() ?? new OrderDeliveryCollection();
        $isFullRefund = ($requestAmount === null && count($items) === 0);
        $amount = (float) $requestAmount;
        $refundItems = [];

        if ($isFullRefund) {
            $existingRefunds = $this->refundGateway->listRefunds($payment->getId(), $salesChannelId);
            $amount = max(0.0, $order->getAmountTotal() - $existingRefunds->getSumRefunded() - $existingRefunds->getSumPending());
            $refundItems = $this->buildItemsFromOrder($orderLineItems, $orderDeliveries);
        }

        if (count($items) > 0) {
            [$amount, $refundItems] = $this->buildFromRequestItems($items, $orderLineItems, $orderDeliveries, $orderId, $context);
        }

        $createRefund = new CreateRefund(
            $payment->getId(),
            new Money($amount, $currency),
            $description,
        );

        $refund = $this->refundGateway->createRefund($createRefund, $salesChannelId);

        $refundData = [
            'orderId' => $order->getId(),
            'orderVersionId' => $order->getVersionId(),
            'mollieRefundId' => $refund->getId(),
            'type' => $isFullRefund ? self::TYPE_FULL : self::TYPE_PARTIAL,
            'publicDescription' => $description,
            'internalDescription' => $internalDescription,
        ];

        if (count($refundItems) > 0) {
            $refundData['refundItems'] = $refundItems;
        }

        $this->refundRepository->upsert([$refundData], $context);

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
     * @param array<array{id: string, quantity: int, amount: float, resetStock: int}> $requestItems
     *
     * @return array{float, array<array<string, mixed>>}
     */
    private function buildFromRequestItems(array $requestItems, OrderLineItemCollection $lineItems, OrderDeliveryCollection $deliveries, string $orderId, Context $context): array
    {
        $total = 0.0;
        $result = [];
        $stockAlterations = [];

        foreach ($requestItems as $item) {
            $lineItemId = (string) ($item['id'] ?? '');
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $itemAmount = (float) ($item['amount'] ?? 0.0);

            $lineItem = $lineItems->get($lineItemId);

            if (! $lineItem instanceof OrderLineItemEntity) {
                $delivery = $deliveries->get($lineItemId);

                if ($delivery === null) {
                    throw new \RuntimeException(sprintf('Line item "%s" not found in order "%s"', $lineItemId, $orderId));
                }

                if ($itemAmount <= 0.0) {
                    $itemAmount = $delivery->getShippingCosts()->getTotalPrice();
                }

                $total += $itemAmount;

                $result[] = [
                    'label' => (string) $delivery->getShippingMethod()?->getName(),
                    'quantity' => 1,
                    'amount' => round($itemAmount, Mollie::ROUNDING_PRECISION),
                    'orderLineItemId' => null,
                    'orderLineItemVersionId' => null,
                ];

                continue;
            }

            if ($itemAmount <= 0.0) {
                $itemAmount = $lineItem->getUnitPrice() * $quantity;
            }

            $total += $itemAmount;

            $result[] = [
                'label' => (string) $lineItem->getLabel(),
                'quantity' => $quantity,
                'amount' => round($itemAmount / $quantity, Mollie::ROUNDING_PRECISION),
                'orderLineItemId' => $lineItem->getId(),
                'orderLineItemVersionId' => $lineItem->getVersionId(),
            ];

            $stockQty = (int) ($item['resetStock'] ?? 0);
            $productId = $lineItem->getReferencedId();

            if ($stockQty > 0 && $productId !== null) {
                $stockAlterations[] = new StockAlteration($lineItem->getId(), $productId, $stockQty, 0);
            }
        }

        if (count($stockAlterations) > 0) {
            $this->stockStorage->alter($stockAlterations, $context);
        }

        return [round($total, Mollie::ROUNDING_PRECISION), $result];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function buildItemsFromOrder(OrderLineItemCollection $lineItems, OrderDeliveryCollection $deliveries): array
    {
        $result = [];

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() === LineItem::CREDIT_LINE_ITEM_TYPE) {
                continue;
            }

            $quantity = max(1, $lineItem->getQuantity());

            $result[] = [
                'label' => (string) $lineItem->getLabel(),
                'quantity' => $quantity,
                'amount' => round($lineItem->getTotalPrice() / $quantity, Mollie::ROUNDING_PRECISION),
                'orderLineItemId' => $lineItem->getId(),
                'orderLineItemVersionId' => $lineItem->getVersionId(),
            ];
        }

        foreach ($deliveries as $delivery) {
            $result[] = [
                'label' => (string) $delivery->getShippingMethod()?->getName(),
                'quantity' => 1,
                'amount' => round($delivery->getShippingCosts()->getTotalPrice(), Mollie::ROUNDING_PRECISION),
                'orderLineItemId' => null,
                'orderLineItemVersionId' => null,
            ];
        }

        return $result;
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
        $criteria->addAssociation('lineItems');
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
