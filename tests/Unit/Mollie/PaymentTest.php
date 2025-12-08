<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

#[CoversClass(Payment::class)]
final class PaymentTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $payment = new Payment('tr_test', PaymentMethod::PAYPAL);
        $payment->setStatus(PaymentStatus::PENDING);
        $payment->setFinalizeUrl('http://test.finalize');
        $payment->setCheckoutUrl('http://test.checkout');
        $payment->setCountPayments(2);
        $payment->setThirdPartyPaymentId('test_thirdPartyPaymentId');
        $payment->setShopwareTransaction(new OrderTransactionEntity());
        $payment->setChangePaymentStateUrl('http://test.payment');

        $this->assertSame('tr_test', $payment->getId());
        $this->assertSame(PaymentMethod::PAYPAL, $payment->getMethod());
        $this->assertSame(PaymentStatus::PENDING, $payment->getStatus());
        $this->assertSame('http://test.finalize', $payment->getFinalizeUrl());
        $this->assertSame('http://test.checkout', $payment->getCheckoutUrl());
        $this->assertSame(2, $payment->getCountPayments());
        $this->assertSame('test_thirdPartyPaymentId', $payment->getThirdPartyPaymentId());
        $this->assertInstanceOf(OrderTransactionEntity::class, $payment->getShopwareTransaction());
        $this->assertSame('http://test.payment', $payment->getChangePaymentStateUrl());
    }

    public function testShopwareTransactionIsRemovedInData(): void
    {
        $payment = new Payment('tr_test', PaymentMethod::PAYPAL);
        $payment->setStatus(PaymentStatus::PENDING);
        $payment->setShopwareTransaction(new OrderTransactionEntity());

        $expectedArray = [
            'status' => 'pending',
            'countPayments' => 1,
            'id' => 'tr_test',
            'method' => PaymentMethod::PAYPAL->value
        ];

        $this->assertEquals($expectedArray, $payment->toArray());
    }

    public function testCreatePaymentFromArray(): void
    {
        $body = [
            'id' => 'tr_test',
            'method' => PaymentMethod::PAYPAL->value,
            'status' => PaymentStatus::PAID->value,
            'details' => [
                'paypalReference' => 'thirdPartyPaymentId',
            ],
            '_links' => [
                'checkout' => [
                    'href' => 'http://test.checkout',
                ],
                'changePaymentState' => [
                    'href' => 'http://test.payment'
                ]
            ]
        ];

        $payment = Payment::createFromClientResponse($body);

        $this->assertSame('tr_test', $payment->getId());
        $this->assertSame('paid', $payment->getStatus()->value);
        $this->assertSame('thirdPartyPaymentId', $payment->getThirdPartyPaymentId());
        $this->assertSame('http://test.checkout', $payment->getCheckoutUrl());
        $this->assertSame('http://test.payment', $payment->getChangePaymentStateUrl());
    }
}
