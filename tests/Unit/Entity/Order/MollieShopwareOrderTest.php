<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Entity\Order;

use Mollie\Shopware\Entity\Order\MollieShopwareOrder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

#[CoversClass(MollieShopwareOrder::class)]
final class MollieShopwareOrderTest extends TestCase
{
    public function testGetLatestTransactionReturnsNullWhenNoTransactions(): void
    {
        $order = new OrderEntity();
        $mollieOrder = new MollieShopwareOrder($order);

        $this->assertNull($mollieOrder->getLatestTransaction());
    }

    public function testGetLatestTransactionReturnsSingleTransaction(): void
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('tx-001');
        $transaction->setCreatedAt(new \DateTimeImmutable('2024-01-01'));

        $order = new OrderEntity();
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        $mollieOrder = new MollieShopwareOrder($order);

        $this->assertSame($transaction, $mollieOrder->getLatestTransaction());
    }

    public function testGetLatestTransactionReturnsNewestOne(): void
    {
        $older = new OrderTransactionEntity();
        $older->setId('tx-001');
        $older->setCreatedAt(new \DateTimeImmutable('2024-01-01'));

        $newer = new OrderTransactionEntity();
        $newer->setId('tx-002');
        $newer->setCreatedAt(new \DateTimeImmutable('2024-06-01'));

        $order = new OrderEntity();
        $order->setTransactions(new OrderTransactionCollection([$older, $newer]));

        $mollieOrder = new MollieShopwareOrder($order);

        $this->assertSame($newer, $mollieOrder->getLatestTransaction());
    }

    public function testGetLatestTransactionWithNullCreatedAt(): void
    {
        $withDate = new OrderTransactionEntity();
        $withDate->setId('tx-001');
        $withDate->setCreatedAt(new \DateTimeImmutable('2024-01-01'));

        $withoutDate = new OrderTransactionEntity();
        $withoutDate->setId('tx-002');

        $order = new OrderEntity();
        $order->setTransactions(new OrderTransactionCollection([$withDate, $withoutDate]));

        $mollieOrder = new MollieShopwareOrder($order);

        // The transaction with a date set is newer than null, so withDate should win
        $this->assertNotNull($mollieOrder->getLatestTransaction());
    }
}
