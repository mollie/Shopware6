<?php
declare(strict_types=1);

namespace Mollie\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

final class PaymentTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $payment = new Payment('tr_test', 'test');
        $payment->setStatus(new PaymentStatus(PaymentStatus::PENDING));
        $payment->setFinalizeUrl('http://test.finalize');
        $payment->setCheckoutUrl('http://test.checkout');
        $payment->setCountPayments(2);
        $payment->setThirdPartyPaymentId('test_thirdPartyPaymentId');
        $payment->setShopwareTransaction(new OrderTransactionEntity());

        $this->assertSame('tr_test', $payment->getId());
        $this->assertSame('test', $payment->getMethod());
        $this->assertSame(PaymentStatus::PENDING, (string) $payment->getStatus());
        $this->assertSame('http://test.finalize', $payment->getFinalizeUrl());
        $this->assertSame('http://test.checkout', $payment->getCheckoutUrl());
        $this->assertSame(2, $payment->getCountPayments());
        $this->assertSame('test_thirdPartyPaymentId', $payment->getThirdPartyPaymentId());
        $this->assertInstanceOf(OrderTransactionEntity::class, $payment->getShopwareTransaction());
    }

    public function testShopwareTransactionIsRemovedInData(): void
    {
        $payment = new Payment('tr_test', 'test');
        $payment->setStatus(new PaymentStatus(PaymentStatus::PENDING));
        $payment->setShopwareTransaction(new OrderTransactionEntity());

        $expectedArray = [
            'status' => 'pending',
            'countPayments' => 1,
            'id' => 'tr_test',
            'method' => 'test',
            'extensions' => []
        ];

        $this->assertEquals($expectedArray, $payment->toArray());
    }

    public function testCreatePaymentFromArray(): void
    {
        $data = [
            'id' => 'tr_test',
            'method' => 'test',
            'status' => PaymentStatus::PAID,
            'details' => [
                'paypalReference' => 'thirdPartyPaymentId',
            ],
            '_links' => [
                'checkout' => [
                    'href' => 'http://test.checkout',
                ]
            ]
        ];

        $payment = Payment::createFromClientResponse($data);

        $this->assertSame('tr_test', $payment->getId());
        $this->assertSame('paid', (string) $payment->getStatus());
        $this->assertSame('thirdPartyPaymentId', $payment->getThirdPartyPaymentId());
        $this->assertSame('http://test.checkout', $payment->getCheckoutUrl());
    }
}
