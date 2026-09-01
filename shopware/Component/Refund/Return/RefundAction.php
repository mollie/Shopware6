<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Return;

use Mollie\Shopware\Component\Refund\Return\Struct\OrderReturnStruct;
use Mollie\Shopware\Component\Refund\Route\AbstractCreateRefundRoute;
use Mollie\Shopware\Component\Refund\Route\CreateRefundRoute;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Refunds what a return of the Return Management asks for. Not final: OrderReturnSubscriber is unit
 * tested against a subclass of this action.
 */
class RefundAction extends AbstractReturnAction
{
    private const STATE_DONE = 'done';

    public function __construct(
        #[Autowire(service: CreateRefundRoute::class)]
        private readonly AbstractCreateRefundRoute $createRefundRoute,
        #[Autowire(service: OrderReturnLoader::class)]
        OrderReturnLoaderInterface $orderReturnLoader,
        #[Autowire(service: SettingsService::class)]
        AbstractSettingsService $settingsService,
        #[Autowire(service: 'monolog.logger.mollie')]
        LoggerInterface $logger,
    ) {
        parent::__construct($orderReturnLoader, $settingsService, $logger);
    }

    /**
     * The return entered the done state, so the merchant decided the goods are back.
     */
    public function execute(string $returnId, Context $context): void
    {
        $orderReturn = $this->loadReturn($returnId, $context);
        if ($orderReturn === null) {
            return;
        }

        $this->refund($orderReturn, $context, 'OrderReturn - Refund creation triggered');
    }

    /**
     * The return was inserted already carrying the done state, so no transition ever fires for it.
     * The state is checked before the order on purpose: a return that is not done is reported as
     * such even when it carries no order at all.
     */
    public function executeOnCreate(string $returnId, Context $context): void
    {
        $orderReturn = $this->loadReturn($returnId, $context);
        if ($orderReturn === null) {
            return;
        }

        $state = $orderReturn->getState();
        if ($state !== self::STATE_DONE) {
            $this->logger->debug('OrderReturn - Return not in done state, skipping refund', [
                'returnId' => $returnId,
                'state' => $state,
            ]);

            return;
        }

        $this->refund($orderReturn, $context, 'OrderReturn - Return created as done, refund creation triggered');
    }

    private function refund(OrderReturnStruct $orderReturn, Context $context, string $message): void
    {
        $order = $this->resolveOrder($orderReturn);
        if ($order === null) {
            return;
        }

        $logData = $this->buildLogData($orderReturn, $order);
        $this->logger->info($message, $logData);

        $items = $this->buildItems($orderReturn, $order);
        $description = $orderReturn->getInternalComment();
        // the total is only written when the return was recalculated, so fall back to its positions
        $amount = $orderReturn->getAmountTotal() ?? (float) array_sum(array_column($items, 'amount'));

        $logData['itemCount'] = count($items);
        $logData['amount'] = $amount;
        $logData['description'] = $description;
        $this->logger->info('OrderReturn - Sending refund request', $logData);

        // the same request the Refund Manager sends: the amount decides what is refunded, the items
        // only carry the composition and the stock. Without it Mollie recalculates the refund from
        // the lines of the order and ignores what the return asks for.
        $request = new Request([], [
            'orderId' => $order->getId(),
            'amount' => $amount,
            'description' => '',
            'internalDescription' => $description,
            'returnId' => $orderReturn->getId(),
            'items' => $items,
        ]);

        try {
            $this->createRefundRoute->create($request, $this->liveContext($context));
            $this->logger->info('OrderReturn - Refund created successfully', $logData);
        } catch (\Throwable $e) {
            $logData['error'] = $e->getMessage();
            $this->logger->error('OrderReturn - Refund creation failed', $logData);
        }
    }

    /**
     * @return array<array{id: string, quantity: int, amount: float, resetStock: int, label: string}>
     */
    private function buildItems(OrderReturnStruct $orderReturn, OrderEntity $order): array
    {
        $items = [];
        $orderLineItems = $order->getLineItems() ?? new OrderLineItemCollection();

        foreach ($orderReturn->getLineItems() as $lineItem) {
            $lineItemId = $lineItem->getOrderLineItemId();

            $items[] = [
                'id' => $lineItemId,
                'quantity' => $lineItem->getQuantity(),
                'amount' => $lineItem->getRefundAmount(),
                // neither the Return Management nor the core puts the returned goods back into
                // stock, so the quantity the return asks for is ours to restock
                'resetStock' => $lineItem->getRestockQuantity(),
                'label' => $orderLineItems->get($lineItemId)?->getLabel() ?? '',
            ];
        }

        $deliveries = $order->getDeliveries();
        if (! $deliveries instanceof OrderDeliveryCollection) {
            return $items;
        }

        $returnShippingValue = $orderReturn->getShippingCostsTotal();
        if ($returnShippingValue <= 0.0) {
            return $items;
        }

        foreach ($deliveries as $delivery) {
            $shippingCosts = $delivery->getShippingCosts();
            if ($shippingCosts->getTotalPrice() <= 0) {
                continue;
            }
            $items[] = [
                'id' => $delivery->getId(),
                'quantity' => 1,
                'amount' => $returnShippingValue,
                'resetStock' => 0,
                'label' => (string) $delivery->getShippingMethod()?->getName(),
            ];
            break;
        }

        return $items;
    }
}
