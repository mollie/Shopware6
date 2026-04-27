<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Transaction;

use Mollie\Shopware\Component\Transaction\PaymentTransactionStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

#[CoversClass(PaymentTransactionStruct::class)]
final class PaymentTransactionStructTest extends TestCase
{
    public function testGetters(): void
    {
        $order = new OrderEntity();
        $order->setId('order-001');

        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('tx-001');

        $struct = new PaymentTransactionStruct(
            'tx-001',
            'https://shop.example.com/return',
            $order,
            $orderTransaction
        );

        $this->assertSame('tx-001', $struct->getOrderTransactionId());
        $this->assertSame('https://shop.example.com/return', $struct->getReturnUrl());
        $this->assertSame($order, $struct->getOrder());
        $this->assertSame($orderTransaction, $struct->getOrderTransaction());
    }
}
