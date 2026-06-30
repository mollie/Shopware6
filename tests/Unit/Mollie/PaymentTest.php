<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;

#[CoversClass(Payment::class)]
final class PaymentTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $payment = new Payment('tr_test');
        $payment->setMethod(PaymentMethod::PAYPAL);
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
        $payment = new Payment('tr_test');
        $payment->setMethod(PaymentMethod::PAYPAL);
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

    public function testPaidPaymentHasNoChargeback(): void
    {
        $payment = new Payment('tr_test');
        $payment->setStatus(PaymentStatus::PAID);

        $this->assertFalse($payment->hasChargeback());
        $this->assertSame('paid', $payment->getStatus()->getShopwareHandlerMethod());
        $this->assertSame(OrderTransactionStates::STATE_PAID, $payment->getStatus()->getShopwarePaymentStatus());
    }

    public function testChargebackIsDerivedFromAmountChargedBack(): void
    {
        $body = [
            'id' => 'tr_test',
            'method' => PaymentMethod::PAYPAL->value,
            'status' => PaymentStatus::PAID->value,
            'amount' => ['value' => '10.00', 'currency' => 'EUR'],
            'amountChargedBack' => ['value' => '10.00', 'currency' => 'EUR'],
        ];

        $payment = Payment::createFromClientResponse($body);

        $this->assertTrue($payment->hasChargeback());
        $this->assertSame(PaymentStatus::CHARGEBACK, $payment->getStatus());
        $this->assertSame('chargeback', $payment->getStatus()->getShopwareHandlerMethod());
        $this->assertSame(OrderTransactionStates::STATE_CHARGEBACK, $payment->getStatus()->getShopwarePaymentStatus());
    }

    public function testZeroAmountChargedBackIsNoChargeback(): void
    {
        $body = [
            'id' => 'tr_test',
            'method' => PaymentMethod::PAYPAL->value,
            'status' => PaymentStatus::PAID->value,
            'amount' => ['value' => '10.00', 'currency' => 'EUR'],
            'amountChargedBack' => ['value' => '0.00', 'currency' => 'EUR'],
        ];

        $payment = Payment::createFromClientResponse($body);

        $this->assertFalse($payment->hasChargeback());
        $this->assertSame('paid', $payment->getStatus()->getShopwareHandlerMethod());
        $this->assertSame(OrderTransactionStates::STATE_PAID, $payment->getStatus()->getShopwarePaymentStatus());
    }

    public function testFullRefundIsDerivedFromAmountRemaining(): void
    {
        $body = [
            'id' => 'tr_test',
            'method' => PaymentMethod::PAYPAL->value,
            'status' => PaymentStatus::PAID->value,
            'amount' => ['value' => '10.00', 'currency' => 'EUR'],
            'amountRefunded' => ['value' => '10.00', 'currency' => 'EUR'],
            'amountRemaining' => ['value' => '0.00', 'currency' => 'EUR'],
        ];

        $payment = Payment::createFromClientResponse($body);

        $this->assertTrue($payment->isFullyRefunded());
        $this->assertFalse($payment->isPartiallyRefunded());
        $this->assertSame(PaymentStatus::REFUNDED, $payment->getStatus());
        $this->assertSame('refund', $payment->getStatus()->getShopwareHandlerMethod());
        $this->assertSame(OrderTransactionStates::STATE_REFUNDED, $payment->getStatus()->getShopwarePaymentStatus());
    }

    public function testPartialRefundIsDerivedFromAmountRefunded(): void
    {
        $body = [
            'id' => 'tr_test',
            'method' => PaymentMethod::PAYPAL->value,
            'status' => PaymentStatus::PAID->value,
            'amount' => ['value' => '10.00', 'currency' => 'EUR'],
            'amountRefunded' => ['value' => '4.00', 'currency' => 'EUR'],
            'amountRemaining' => ['value' => '6.00', 'currency' => 'EUR'],
        ];

        $payment = Payment::createFromClientResponse($body);

        $this->assertTrue($payment->isPartiallyRefunded());
        $this->assertFalse($payment->isFullyRefunded());
        $this->assertSame(PaymentStatus::PARTIALLY_REFUNDED, $payment->getStatus());
        $this->assertSame('refundPartially', $payment->getStatus()->getShopwareHandlerMethod());
        $this->assertSame(OrderTransactionStates::STATE_PARTIALLY_REFUNDED, $payment->getStatus()->getShopwarePaymentStatus());
    }

    public function testZeroAmountRefundedIsNoRefund(): void
    {
        $body = [
            'id' => 'tr_test',
            'method' => PaymentMethod::PAYPAL->value,
            'status' => PaymentStatus::PAID->value,
            'amount' => ['value' => '10.00', 'currency' => 'EUR'],
            'amountRefunded' => ['value' => '0.00', 'currency' => 'EUR'],
            'amountRemaining' => ['value' => '10.00', 'currency' => 'EUR'],
        ];

        $payment = Payment::createFromClientResponse($body);

        $this->assertFalse($payment->hasRefund());
        $this->assertFalse($payment->isPartiallyRefunded());
        $this->assertFalse($payment->isFullyRefunded());
        $this->assertSame('paid', $payment->getStatus()->getShopwareHandlerMethod());
        $this->assertSame(OrderTransactionStates::STATE_PAID, $payment->getStatus()->getShopwarePaymentStatus());
    }

    public function testChargebackTakesPrecedenceOverRefund(): void
    {
        $body = [
            'id' => 'tr_test',
            'method' => PaymentMethod::PAYPAL->value,
            'status' => PaymentStatus::PAID->value,
            'amount' => ['value' => '10.00', 'currency' => 'EUR'],
            'amountRefunded' => ['value' => '4.00', 'currency' => 'EUR'],
            'amountRemaining' => ['value' => '0.00', 'currency' => 'EUR'],
            'amountChargedBack' => ['value' => '6.00', 'currency' => 'EUR'],
        ];

        $payment = Payment::createFromClientResponse($body);

        $this->assertTrue($payment->hasChargeback());
        $this->assertSame(PaymentStatus::CHARGEBACK, $payment->getStatus());
        $this->assertSame('chargeback', $payment->getStatus()->getShopwareHandlerMethod());
        $this->assertSame(OrderTransactionStates::STATE_CHARGEBACK, $payment->getStatus()->getShopwarePaymentStatus());
    }
}
