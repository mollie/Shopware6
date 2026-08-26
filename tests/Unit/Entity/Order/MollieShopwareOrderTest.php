<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Entity\Order;

use Mollie\Shopware\Entity\Order\MollieShopwareOrder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

/**
 * When a customer retries a payment, Shopware adds another transaction to the order. This wrapper
 * answers which one of them is the newest - the one a webhook has to be matched against.
 */
#[CoversClass(MollieShopwareOrder::class)]
final class MollieShopwareOrderTest extends TestCase
{
    public function testTheNewestTransactionIsTheLatestOne(): void
    {
        $older = $this->transaction('older', 1000);
        $newer = $this->transaction('newer', 3000);

        // Insertion order deliberately differs from the creation order.
        $order = $this->order(new OrderTransactionCollection([$newer, $older]));

        $this->assertSame($newer, (new MollieShopwareOrder($order))->getLatestTransaction());
    }

    public function testTheOnlyTransactionIsTheLatestOne(): void
    {
        $only = $this->transaction('only', 1000);

        $order = $this->order(new OrderTransactionCollection([$only]));

        $this->assertSame($only, (new MollieShopwareOrder($order))->getLatestTransaction());
    }

    public function testAnOrderWithoutAnyTransactionHasNoLatestOne(): void
    {
        $order = $this->order(new OrderTransactionCollection());

        $this->assertNull((new MollieShopwareOrder($order))->getLatestTransaction());
    }

    /**
     * An order loaded without its transactions association cannot answer the question at all.
     */
    public function testAnOrderWhoseTransactionsWereNotLoadedHasNoLatestOne(): void
    {
        $order = new OrderEntity();
        $order->setId('order-1');

        $this->assertNull((new MollieShopwareOrder($order))->getLatestTransaction());
    }

    private function order(OrderTransactionCollection $transactions): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setTransactions($transactions);

        return $order;
    }

    private function transaction(string $id, int $createdAtTimestamp): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId($id);
        $transaction->setCreatedAt((new \DateTimeImmutable())->setTimestamp($createdAtTimestamp));

        return $transaction;
    }
}
