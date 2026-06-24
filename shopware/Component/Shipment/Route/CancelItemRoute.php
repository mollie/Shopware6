<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Shipment\CancelItemEvent;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockAlteration;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
final class CancelItemRoute
{
    /**
     * @param EntityRepository<OrderLineItemCollection> $orderLineRepository
     */
    public function __construct(
        #[Autowire(service: 'order_line_item.repository')]
        private readonly EntityRepository $orderLineRepository,
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        #[Autowire(service: StockStorage::class)]
        private readonly AbstractStockStorage $stockStorage,
        #[Autowire(service: 'event_dispatcher')]
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route(path: '/api/_action/mollie/cancel/item', name: 'api.action.mollie.cancel.item', methods: ['POST'])]
    public function cancel(Request $request, Context $context): JsonResponse
    {
        $shopwareLineId = (string) $request->get('shopwareLineId', '');
        $quantity = (int) $request->get('quantity', 0);
        $resetStock = (bool) $request->get('resetStock', false);

        if ($shopwareLineId === '') {
            return new JsonResponse(['success' => false, 'message' => 'Missing shopwareLineId'], 400);
        }

        if ($quantity <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'quantityZero'], 400);
        }

        $criteria = new Criteria([$shopwareLineId]);
        $criteria->getAssociation('order.transactions')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
            ->setLimit(1)
        ;
        $criteria->addAssociation('order.lineItems');

        $lineItem = $this->orderLineRepository->search($criteria, $context)->first();

        if (! $lineItem instanceof OrderLineItemEntity) {
            return new JsonResponse(['success' => false, 'message' => 'invalidShopwareLineId'], 400);
        }

        $order = $lineItem->getOrder();
        if ($order === null) {
            return new JsonResponse(['success' => false, 'message' => 'invalidShopwareLineId'], 400);
        }

        $salesChannelId = $order->getSalesChannelId();
        $orderNumber = (string) $order->getOrderNumber();

        $latestTransaction = $order->getTransactions()?->first();
        if ($latestTransaction === null) {
            return new JsonResponse(['success' => false, 'message' => 'noTransaction'], 400);
        }

        $payment = $latestTransaction->getExtension(Mollie::EXTENSION);
        if (! $payment instanceof Payment) {
            return new JsonResponse(['success' => false, 'message' => 'notMollieOrder'], 400);
        }

        $mollieOrderId = $payment->getOrderId();

        if ($resetStock) {
            $productId = $lineItem->getReferencedId();
            if ($productId !== null) {
                $this->stockStorage->alter(
                    [new StockAlteration($shopwareLineId, $productId, $quantity, 0)],
                    $context,
                );
            }
        }

        if ($mollieOrderId !== null && $mollieOrderId !== '') {
            return $this->cancelOrdersApi($lineItem, $mollieOrderId, $quantity, $orderNumber, $salesChannelId, $latestTransaction->getId(), $context);
        }

        return $this->cancelPaymentsApi($lineItem, $payment, $quantity, $shopwareLineId, $orderNumber, $salesChannelId, $order->getLineItems() ?? new OrderLineItemCollection(), $latestTransaction->getId(), $context);
    }

    private function cancelOrdersApi(
        OrderLineItemEntity $lineItem,
        string $mollieOrderId,
        int $quantity,
        string $orderNumber,
        string $salesChannelId,
        string $transactionId,
        Context $context
    ): JsonResponse {
        $customFields = $lineItem->getCustomFields() ?? [];
        $mollieLineId = (string) (($customFields[Mollie::EXTENSION] ?? [])['order_line_id'] ?? '');

        if ($mollieLineId === '') {
            return new JsonResponse(['success' => false, 'message' => 'invalidLine'], 400);
        }

        $this->mollieGateway->cancelOrderLines($mollieOrderId, $mollieLineId, $quantity, $orderNumber, $salesChannelId);

        $this->eventDispatcher->dispatch(new CancelItemEvent($transactionId, $context));

        return new JsonResponse([
            'success' => true,
            'message' => '',
            'data' => ['id' => $mollieLineId, 'quantity' => $quantity],
        ]);
    }

    private function cancelPaymentsApi(
        OrderLineItemEntity $lineItem,
        Payment $payment,
        int $quantity,
        string $shopwareLineId,
        string $orderNumber,
        string $salesChannelId,
        OrderLineItemCollection $allLineItems,
        string $transactionId,
        Context $context
    ): JsonResponse {
        $customFields = $lineItem->getCustomFields() ?? [];
        $mollieExtension = $customFields[Mollie::EXTENSION] ?? [];

        $shippedQty = (int) ($mollieExtension['quantity'] ?? 0);
        $alreadyCancelled = (int) ($mollieExtension['cancelled_quantity'] ?? 0);
        $cancelable = max(0, $lineItem->getQuantity() - $shippedQty - $alreadyCancelled);

        if ($quantity > $cancelable) {
            return new JsonResponse(['success' => false, 'message' => 'quantityTooHigh'], 400);
        }

        $newCancelledQty = $alreadyCancelled + $quantity;

        $this->orderLineRepository->upsert([
            [
                'id' => $shopwareLineId,
                'customFields' => [
                    Mollie::EXTENSION => array_merge($mollieExtension, ['cancelled_quantity' => $newCancelledQty]),
                ],
            ],
        ], $context);

        if ($this->isFullyHandled($allLineItems, $shopwareLineId, $newCancelledQty)) {
            $this->mollieGateway->releaseAuthorization($payment->getId(), $orderNumber, $salesChannelId);
        }

        $this->eventDispatcher->dispatch(new CancelItemEvent($transactionId, $context));

        return new JsonResponse([
            'success' => true,
            'message' => '',
            'data' => ['id' => $shopwareLineId, 'quantity' => $quantity],
        ]);
    }

    private function isFullyHandled(OrderLineItemCollection $allLineItems, string $updatedLineId, int $updatedCancelledQty): bool
    {
        foreach ($allLineItems as $lineItem) {
            if ($lineItem->getQuantity() <= 0) {
                continue;
            }

            $fields = $lineItem->getCustomFields()[Mollie::EXTENSION] ?? [];
            $shipped = (int) ($fields['quantity'] ?? 0);
            $cancelled = $lineItem->getId() === $updatedLineId
                ? $updatedCancelledQty
                : (int) ($fields['cancelled_quantity'] ?? 0);

            if (($shipped + $cancelled) < $lineItem->getQuantity()) {
                return false;
            }
        }

        return true;
    }
}
