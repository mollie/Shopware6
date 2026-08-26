<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Mollie\RoundingDifferenceFixer;
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

    public function testGetVarsCoversEverySerializedKey(): void
    {
        $payment = new Payment('tr_test');
        $payment->setMethod(PaymentMethod::PAYPAL);
        $payment->setStatus(PaymentStatus::PENDING);

        $missing = array_diff(array_keys($payment->jsonSerialize()), array_keys($payment->getVars()));

        $this->assertSame([], $missing);
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

    public function testVoucherAmountIsSummedFromDetails(): void
    {
        $body = [
            'id' => 'tr_test',
            'method' => PaymentMethod::PAYPAL->value,
            'status' => PaymentStatus::PAID->value,
            'details' => [
                'vouchers' => [
                    ['amount' => ['value' => '5.00', 'currency' => 'EUR']],
                    ['amount' => ['value' => '2.50', 'currency' => 'EUR']],
                ],
            ],
        ];

        $payment = Payment::createFromClientResponse($body);

        $this->assertSame(7.5, $payment->getVoucherAmount());
    }

    public function testVoucherAmountDefaultsToZeroWithoutVouchers(): void
    {
        $payment = Payment::createFromClientResponse([
            'id' => 'tr_test',
            'method' => PaymentMethod::PAYPAL->value,
            'status' => PaymentStatus::PAID->value,
        ]);

        $this->assertSame(0.0, $payment->getVoucherAmount());
    }

    public function testRoundingDiffIsReadFromRoundingLine(): void
    {
        $body = [
            'id' => 'tr_test',
            'method' => PaymentMethod::PAYPAL->value,
            'status' => PaymentStatus::PAID->value,
            'lines' => [
                [
                    'name' => 'Product',
                    'totalAmount' => ['value' => '19.99', 'currency' => 'EUR'],
                    'metadata' => ['orderLineItemId' => 'abc'],
                ],
                [
                    'name' => RoundingDifferenceFixer::DEFAULT_TITLE,
                    'totalAmount' => ['value' => '0.01', 'currency' => 'EUR'],
                    'metadata' => ['type' => RoundingDifferenceFixer::METADATA_TYPE],
                ],
            ],
        ];

        $payment = Payment::createFromClientResponse($body);

        $this->assertSame(0.01, $payment->getRoundingDiff());
    }

    public function testRoundingDiffIsReadFromSkuWhenMetadataIsAbsent(): void
    {
        $body = [
            'id' => 'tr_test',
            'method' => PaymentMethod::PAYPAL->value,
            'status' => PaymentStatus::PAID->value,
            'lines' => [
                [
                    'name' => 'Product',
                    'sku' => 'MOL_REGULAR',
                    'totalAmount' => ['value' => '19.99', 'currency' => 'EUR'],
                ],
                [
                    'name' => RoundingDifferenceFixer::DEFAULT_TITLE,
                    'sku' => RoundingDifferenceFixer::SKU,
                    'totalAmount' => ['value' => '0.01', 'currency' => 'EUR'],
                ],
            ],
        ];

        $payment = Payment::createFromClientResponse($body);

        $this->assertSame(0.01, $payment->getRoundingDiff());
    }

    public function testRoundingDiffDefaultsToZeroWithoutRoundingLine(): void
    {
        $payment = Payment::createFromClientResponse([
            'id' => 'tr_test',
            'method' => PaymentMethod::PAYPAL->value,
            'status' => PaymentStatus::PAID->value,
        ]);

        $this->assertSame(0.0, $payment->getRoundingDiff());
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

    public function testRefundableAmountIsTheRemainingAmount(): void
    {
        $body = [
            'id' => 'tr_test',
            'method' => PaymentMethod::KLARNA->value,
            'status' => PaymentStatus::PAID->value,
            'amount' => ['value' => '84.49', 'currency' => 'EUR'],
            'amountRefunded' => ['value' => '0.00', 'currency' => 'EUR'],
            'amountRemaining' => ['value' => '54.62', 'currency' => 'EUR'],
            'amountCaptured' => ['value' => '54.62', 'currency' => 'EUR'],
        ];

        $payment = Payment::createFromClientResponse($body);

        $this->assertSame(54.62, $payment->getRefundableAmount());
    }

    public function testRefundableAmountFallsBackToTheCapturedAmount(): void
    {
        $body = [
            'id' => 'tr_test',
            'method' => PaymentMethod::KLARNA->value,
            'status' => PaymentStatus::PAID->value,
            'amount' => ['value' => '84.49', 'currency' => 'EUR'],
            'amountRefunded' => ['value' => '10.00', 'currency' => 'EUR'],
            'amountCaptured' => ['value' => '50.00', 'currency' => 'EUR'],
        ];

        $payment = Payment::createFromClientResponse($body);

        $this->assertSame(40.0, $payment->getRefundableAmount());
    }

    public function testRefundableAmountIsUnknownWithoutCaptureInformation(): void
    {
        $body = [
            'id' => 'tr_test',
            'method' => PaymentMethod::KLARNA->value,
            'status' => PaymentStatus::AUTHORIZED->value,
            'amount' => ['value' => '84.49', 'currency' => 'EUR'],
            'amountCaptured' => ['value' => '0.00', 'currency' => 'EUR'],
        ];

        $payment = Payment::createFromClientResponse($body);

        $this->assertNull($payment->getRefundableAmount());
    }

    public function testCreditCardDetailsAreUnknownWithoutALabel(): void
    {
        $this->assertNull((new Payment('tr_test'))->getCreditCardDetails());
    }

    public function testCreditCardDetailsAreExposedAsOneStructure(): void
    {
        $payment = new Payment('tr_test');
        $payment->setCreditCardLabel('Visa');
        $payment->setCreditCardNumber('************1234');
        $payment->setCreditCardHolder('John Doe');

        $this->assertSame([
            'label' => 'Visa',
            'number' => '************1234',
            'holder' => 'John Doe',
        ], $payment->getCreditCardDetails());
        $this->assertSame('Visa', $payment->getCreditCardLabel());
        $this->assertSame('************1234', $payment->getCreditCardNumber());
        $this->assertSame('John Doe', $payment->getCreditCardHolder());
    }

    public function testPaypalDetailsAreUnknownWithoutAPayerId(): void
    {
        $this->assertNull((new Payment('tr_test'))->getPaypalDetails());
    }

    public function testPaypalDetailsExposeThePayerId(): void
    {
        $payment = new Payment('tr_test');
        $payment->setPaypalPayerId('PAYER-1');

        $this->assertSame(['payerId' => 'PAYER-1'], $payment->getPaypalDetails());
        $this->assertSame('PAYER-1', $payment->getPaypalPayerId());
    }

    public function testBankTransferDetailsAreUnknownWithoutABankAccount(): void
    {
        $payment = new Payment('tr_test');
        $payment->setBankName('Test Bank');

        $this->assertNull($payment->getBankTransferDetails());
    }

    public function testBankTransferDetailsAreExposedAsOneStructure(): void
    {
        $payment = new Payment('tr_test');
        $payment->setBankName('Test Bank');
        $payment->setBankAccount('NL55INGB0000000000');
        $payment->setBankBic('INGBNL2A');
        $payment->setTransferReference('RF12-3456');
        $payment->setConsumerName('John Doe');
        $payment->setConsumerAccount('NL02ABNA0123456789');
        $payment->setConsumerBic('ABNANL2A');

        $this->assertSame([
            'bankName' => 'Test Bank',
            'bankAccount' => 'NL55INGB0000000000',
            'bankBic' => 'INGBNL2A',
            'transferReference' => 'RF12-3456',
            'consumerName' => 'John Doe',
            'consumerAccount' => 'NL02ABNA0123456789',
            'consumerBic' => 'ABNANL2A',
        ], $payment->getBankTransferDetails());
    }

    public function testRefundCaptureAndShipmentIdsAreCollected(): void
    {
        $payment = new Payment('tr_test');
        $payment->addRefundId('re_1');
        $payment->addCaptureId('cpt_1');
        $payment->addShipmentId('shp_1');

        $this->assertSame(['re_1'], $payment->getRefundIds());
        $this->assertSame(['cpt_1'], $payment->getCaptureIds());
        $this->assertSame(['shp_1'], $payment->getShipmentIds());
    }

    public function testRemovingARefundIdKeepsTheOtherIds(): void
    {
        $payment = new Payment('tr_test');
        $payment->setRefundIds(['re_1', 're_2']);

        $payment->removeRefundId('re_1');

        $this->assertSame(['re_2'], $payment->getRefundIds());
    }

    public function testARefundIdIsAlsoRemovedInItsExportedHyphenForm(): void
    {
        $payment = new Payment('tr_test');
        $payment->setRefundIds(['re_1', 're_2']);

        $payment->removeRefundId('re-1');

        $this->assertSame(['re_2'], $payment->getRefundIds());
    }

    public function testExportedIdsUseHyphensAndAreMergedIntoOneValue(): void
    {
        $payment = new Payment('tr_test');
        $payment->setStatus(PaymentStatus::PAID);
        $payment->setRefundIds(['re_1', 're_2']);
        $payment->setCaptureIds(['cpt_1']);
        $payment->setShipmentIds(['shp_1', 'shp_2']);

        $data = $payment->toArray();

        $this->assertSame('re-1, re-2', $data['refundIds']);
        $this->assertSame('cpt-1', $data['captureIds']);
        $this->assertSame('shp-1, shp-2', $data['shipmentIds']);
    }

    public function testEmptyIdListsAreNotExported(): void
    {
        $payment = new Payment('tr_test');
        $payment->setStatus(PaymentStatus::PAID);

        $data = $payment->toArray();

        $this->assertArrayNotHasKey('refundIds', $data);
        $this->assertArrayNotHasKey('captureIds', $data);
        $this->assertArrayNotHasKey('shipmentIds', $data);
    }

    public function testPaymentIsNotCancelableByDefault(): void
    {
        $this->assertFalse((new Payment('tr_test'))->isCancelable());
    }

    public function testCancelableFlagIsKept(): void
    {
        $payment = new Payment('tr_test');
        $payment->setCancelable(true);

        $this->assertTrue($payment->isCancelable());
    }

    public function testPaymentIsNotReconciledByDefault(): void
    {
        $this->assertFalse((new Payment('tr_test'))->isReconciled());
    }

    public function testReconciledFlagIsKept(): void
    {
        $payment = new Payment('tr_test');
        $payment->setReconciled(true);

        $this->assertTrue($payment->isReconciled());
    }

    public function testMollieReferencesAreKept(): void
    {
        $createdAt = new \DateTimeImmutable('2026-01-15 10:00:00');

        $payment = new Payment('tr_test');
        $payment->setOrderId('ord_1');
        $payment->setPaymentLinkId('pl_1');
        $payment->setAuthenticationId('auth_1');
        $payment->setSubscriptionId('sub_1');
        $payment->setProfileId('pfl_1');
        $payment->setCustomerId('cst_1');
        $payment->setMandateId('mdt_1');
        $payment->setCreatedAt($createdAt);

        $this->assertSame('ord_1', $payment->getOrderId());
        $this->assertSame('pl_1', $payment->getPaymentLinkId());
        $this->assertSame('auth_1', $payment->getAuthenticationId());
        $this->assertSame('sub_1', $payment->getSubscriptionId());
        $this->assertSame('pfl_1', $payment->getProfileId());
        $this->assertSame('cst_1', $payment->getCustomerId());
        $this->assertSame('mdt_1', $payment->getMandateId());
        $this->assertSame($createdAt, $payment->getCreatedAt());
    }

    public function testMollieReferencesAreUnknownOnAFreshPayment(): void
    {
        $payment = new Payment('tr_test');

        $this->assertNull($payment->getOrderId());
        $this->assertNull($payment->getPaymentLinkId());
        $this->assertNull($payment->getAuthenticationId());
        $this->assertNull($payment->getSubscriptionId());
        $this->assertNull($payment->getProfileId());
        $this->assertNull($payment->getCustomerId());
        $this->assertNull($payment->getMandateId());
        $this->assertNull($payment->getCreatedAt());
        $this->assertNull($payment->getAmount());
        $this->assertNull($payment->getCapturedAmount());
        $this->assertNull($payment->getAmountRemaining());
        $this->assertNull($payment->getAmountChargedBack());
        $this->assertCount(0, $payment->getRefunds());
    }

    public function testVoucherAmountAndRoundingDifferenceAreKept(): void
    {
        $payment = new Payment('tr_test');
        $payment->setVoucherAmount(12.50);
        $payment->setRoundingDiff(-0.02);

        $this->assertSame(12.50, $payment->getVoucherAmount());
        $this->assertSame(-0.02, $payment->getRoundingDiff());
    }
}
