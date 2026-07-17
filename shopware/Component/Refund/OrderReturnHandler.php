<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund;

use Mollie\Shopware\Component\Mollie\Gateway\RefundGateway;
use Mollie\Shopware\Component\Mollie\Gateway\RefundGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Refund\Controller\RefundController;
use Mollie\Shopware\Component\Transaction\MollieOrderTransactionCollection;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Commercial\ReturnManagement\Entity\OrderReturn\OrderReturnEntity;
use Shopware\Commercial\ReturnManagement\Entity\OrderReturnLineItem\OrderReturnLineItemEntity;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

final class OrderReturnHandler
{
    private readonly bool $featureDisabled;

    /**
     * @param null|EntityRepository<EntityCollection<OrderReturnEntity>> $orderReturnRepository
     */
    public function __construct(
        private readonly RefundController $refundController,
        #[Autowire(service: RefundGateway::class)]
        private readonly RefundGatewayInterface $refundGateway,
        #[Autowire(service: 'order_return.repository')]
        private readonly ?EntityRepository $orderReturnRepository,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
        $this->featureDisabled = $this->orderReturnRepository === null;
    }

    public function return(string $returnId, Context $context): void
    {
        $logData = ['returnId' => $returnId];
        $this->logger->info('OrderReturn - Refund creation triggered', $logData);

        if ($this->featureDisabled) {
            $this->logger->warning('OrderReturn - Feature disabled (SwagCommercial not installed)', $logData);

            return;
        }

        $orderReturn = $this->findReturnById($returnId, $context);
        if ($orderReturn === null) {
            return;
        }

        $order = $orderReturn->getOrder();
        if (! $order instanceof OrderEntity) {
            $this->logger->error('OrderReturn - No order associated with return', $logData);

            return;
        }

        $logData['orderNumber'] = $order->getOrderNumber();
        $logData['orderId'] = $order->getId();

        $this->triggerRefund($returnId, $orderReturn, $order, $context, $logData);
    }

    public function cancel(string $returnId, Context $context): void
    {
        $logData = ['returnId' => $returnId];
        $this->logger->info('OrderReturn - Refund cancellation triggered', $logData);

        if ($this->featureDisabled) {
            $this->logger->warning('OrderReturn - Feature disabled (SwagCommercial not installed)', $logData);

            return;
        }

        $orderReturn = $this->findReturnById($returnId, $context);
        if ($orderReturn === null) {
            return;
        }

        $order = $orderReturn->getOrder();
        if (! $order instanceof OrderEntity) {
            $this->logger->error('OrderReturn - No order associated with return', $logData);

            return;
        }

        $logData['orderNumber'] = $order->getOrderNumber();
        $logData['orderId'] = $order->getId();

        $payment = $this->extractMolliePayment($order);
        if ($payment === null) {
            $this->logger->warning('OrderReturn - No Mollie payment found, skipping cancellation', $logData);

            return;
        }

        $logData['paymentId'] = $payment->getId();

        $orderNumber = (string) $order->getOrderNumber();
        $salesChannelId = (string) $order->getSalesChannelId();

        $this->logger->info('OrderReturn - Listing Mollie refunds to find matching return', $logData);

        try {
            $refunds = $this->refundGateway->listRefunds($payment->getId(), $orderNumber, $salesChannelId);
            $refund = $refunds->findByReturnId($returnId);

            if ($refund === null) {
                $this->logger->warning('OrderReturn - No matching Mollie refund found for returnId', $logData);

                return;
            }

            $logData['refundId'] = $refund->getId();
            $this->logger->info('OrderReturn - Found matching Mollie refund, cancelling', $logData);

            $request = new Request([], [
                'orderId' => $order->getId(),
                'refundId' => $refund->getId(),
            ]);

            $this->refundController->cancel($request, $context);

            $this->logger->info('OrderReturn - Refund cancelled successfully', $logData);
        } catch (\Throwable $e) {
            $logData['error'] = $e->getMessage();
            $this->logger->error('OrderReturn - Refund cancellation failed', $logData);
        }
    }

    public function returnOnCreatedAsDone(string $returnId, Context $context): void
    {
        $logData = ['returnId' => $returnId];
        $this->logger->info('OrderReturn - Return created, checking state', $logData);

        if ($this->featureDisabled) {
            $this->logger->warning('OrderReturn - Feature disabled (SwagCommercial not installed)', $logData);

            return;
        }

        $orderReturn = $this->findReturnById($returnId, $context);
        if ($orderReturn === null) {
            return;
        }

        $state = $orderReturn->getState();
        if ($state === null || $state->getTechnicalName() !== 'done') {
            $logData['state'] = $state?->getTechnicalName();
            $this->logger->debug('OrderReturn - Return not in done state, skipping refund', $logData);

            return;
        }

        $order = $orderReturn->getOrder();
        if (! $order instanceof OrderEntity) {
            $this->logger->error('OrderReturn - No order associated with return', $logData);

            return;
        }

        $logData['orderNumber'] = $order->getOrderNumber();
        $logData['orderId'] = $order->getId();

        $this->triggerRefund($returnId, $orderReturn, $order, $context, $logData);
    }

    /**
     * @param array<string, mixed> $logData
     */
    private function triggerRefund(string $returnId, OrderReturnEntity $orderReturn, OrderEntity $order, Context $context, array $logData): void
    {
        $items = $this->buildItemsFromReturn($orderReturn, $order);
        $description = (string) $orderReturn->getInternalComment();

        $logData['itemCount'] = count($items);
        $logData['description'] = $description;
        $this->logger->info('OrderReturn - Sending refund request', $logData);

        $request = new Request([], [
            'orderId' => $order->getId(),
            'description' => '',
            'internalDescription' => $description,
            'returnId' => $returnId,
            'items' => $items,
        ]);

        try {
            $this->refundController->create($request, $context);
            $this->logger->info('OrderReturn - Refund created successfully', $logData);
        } catch (\Throwable $e) {
            $logData['error'] = $e->getMessage();
            $this->logger->error('OrderReturn - Refund creation failed', $logData);
        }
    }

    /**
     * @return array<array{id: string, quantity: int, amount: float, resetStock: int, label: string}>
     */
    private function buildItemsFromReturn(OrderReturnEntity $orderReturn, OrderEntity $order): array
    {
        $items = [];
        $orderLineItems = $order->getLineItems() ?? new OrderLineItemCollection();

        /** @var OrderReturnLineItemEntity $lineItem */
        foreach ($orderReturn->getLineItems() as $lineItem) {
            $lineItemId = (string) $lineItem->getOrderLineItemId();
            $label = $orderLineItems->get($lineItemId)?->getLabel() ?? '';

            $items[] = [
                'id' => $lineItemId,
                'quantity' => $lineItem->getQuantity(),
                'amount' => $lineItem->getRefundAmount(),
                'resetStock' => 0,
                'label' => $label,
            ];
        }

        $deliveries = $order->getDeliveries();
        if (! $deliveries instanceof OrderDeliveryCollection) {
            return $items;
        }

        $returnShippingCosts = $orderReturn->getShippingCosts();
        $returnShippingValue = $returnShippingCosts instanceof CalculatedPrice
            ? $returnShippingCosts->getTotalPrice()
            : 0.0;

        if ($returnShippingValue > 0.0) {
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
        }

        return $items;
    }

    private function extractMolliePayment(OrderEntity $order): ?Payment
    {
        $transactions = new MollieOrderTransactionCollection($order->getTransactions());
        $transaction = $transactions->getCurrentOrderTransaction();
        if ($transaction === null) {
            return null;
        }

        $payment = $transaction->getExtension(Mollie::EXTENSION);

        return $payment instanceof Payment ? $payment : null;
    }

    private function findReturnById(string $returnId, Context $context): ?OrderReturnEntity
    {
        $criteria = new Criteria([$returnId]);
        $criteria->addAssociation('state');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('order.deliveries.shippingMethod');
        $criteria->addAssociation('order.transactions.stateMachineState');

        $result = $this->orderReturnRepository->search($criteria, $context);

        if ($result->getTotal() === 0) {
            $this->logger->warning('OrderReturn - Return not found', ['returnId' => $returnId]);

            return null;
        }

        return $result->first();
    }
}
