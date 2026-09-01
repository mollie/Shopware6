<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Refund\DAL\Order\OrderExtension;
use Mollie\Shopware\Component\Transaction\Event\RepairLegacyTransactionEvent;
use Mollie\Shopware\Component\Transaction\MollieOrderTransactionCollection;
use Mollie\Shopware\Mollie;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves the order and its Mollie payment for the refund routes. All three of them need the
 * order with the same associations and the payment off its current transaction, so the criteria
 * exists once here rather than in each route.
 *
 * Not final: the routes are unit tested against a fake of this class.
 */
class RefundOrderLoader
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
        #[Autowire(service: MollieGateway::class)]
        private readonly MollieGatewayInterface $mollieGateway,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function load(string $orderId, Context $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems.product');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('currency');
        $criteria->addAssociation(OrderExtension::REFUND_PROPERTY_NAME . '.refundItems');
        $criteria->addAssociation('transactions.stateMachineState');

        /** @var null|OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if (! $order instanceof OrderEntity) {
            throw new \RuntimeException(sprintf('Order "%s" not found', $orderId));
        }

        return $order;
    }

    public function extractPayment(OrderEntity $order, Context $context): Payment
    {
        $payment = $this->findPayment($order, $context);

        if (! $payment instanceof Payment) {
            throw new \RuntimeException(sprintf('No Mollie payment extension found for order "%s"', $order->getId()));
        }

        return $payment;
    }

    public function findPayment(OrderEntity $order, Context $context): ?Payment
    {
        $transactions = new MollieOrderTransactionCollection($order->getTransactions());
        $transaction = $transactions->getCurrentOrderTransaction();
        if ($transaction === null) {
            return null;
        }

        $repairEvent = new RepairLegacyTransactionEvent($transaction, $order, $context);
        $this->eventDispatcher->dispatch($repairEvent);

        $payment = $transaction->getExtension(Mollie::EXTENSION);

        return $payment instanceof Payment ? $payment : null;
    }

    /**
     * Loads the payment from Mollie including its refunds (the gateway embeds them), so a single
     * request covers both the refund list and the current amounts. The transaction extension can
     * not be used for the amounts: it is written during checkout and does not know about later
     * captures or refunds.
     */
    public function loadFresh(Payment $payment, OrderEntity $order): Payment
    {
        return $this->mollieGateway->getPayment(
            $payment->getId(),
            (string) $order->getOrderNumber(),
            (string) $order->getSalesChannelId()
        );
    }
}
