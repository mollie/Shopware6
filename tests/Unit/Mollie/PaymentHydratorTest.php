<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\PaymentHydrator;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Mollie\RoundingDifferenceFixer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentHydrator::class)]
final class PaymentHydratorTest extends TestCase
{
    public function testScalarsAreHydrated(): void
    {
        $body = [
            'id' => 'tr_test',
            'method' => PaymentMethod::PAYPAL->value,
            'status' => PaymentStatus::PAID->value,
            'customerId' => 'cst_1',
            'mandateId' => 'mdt_1',
            'profileId' => 'pfl_1',
            'subscriptionId' => 'sub_1',
            'isCancelable' => true,
            'details' => [
                'paypalReference' => 'thirdPartyPaymentId',
            ],
            '_links' => [
                'checkout' => ['href' => 'http://test.checkout'],
                'changePaymentState' => ['href' => 'http://test.payment'],
            ],
        ];

        $payment = (new PaymentHydrator())->hydrate($body);

        $this->assertSame('tr_test', $payment->getId());
        $this->assertSame(PaymentMethod::PAYPAL, $payment->getMethod());
        $this->assertSame('thirdPartyPaymentId', $payment->getThirdPartyPaymentId());
        $this->assertSame('http://test.checkout', $payment->getCheckoutUrl());
        $this->assertSame('http://test.payment', $payment->getChangePaymentStateUrl());
        $this->assertSame('cst_1', $payment->getCustomerId());
        $this->assertSame('mdt_1', $payment->getMandateId());
        $this->assertSame('pfl_1', $payment->getProfileId());
        $this->assertSame('sub_1', $payment->getSubscriptionId());
        $this->assertTrue($payment->isCancelable());
    }

    public function testCreatedAtIsParsedFromAtom(): void
    {
        $body = [
            'id' => 'tr_test',
            'status' => PaymentStatus::PAID->value,
            'createdAt' => '2026-07-23T10:00:00+00:00',
        ];

        $payment = (new PaymentHydrator())->hydrate($body);

        $createdAt = $payment->getCreatedAt();
        $this->assertInstanceOf(\DateTimeInterface::class, $createdAt);
        $this->assertSame('2026-07-23T10:00:00+00:00', $createdAt->format(\DateTimeInterface::ATOM));
    }

    public function testVoucherAmountIsSummed(): void
    {
        $body = [
            'id' => 'tr_test',
            'status' => PaymentStatus::PAID->value,
            'details' => [
                'vouchers' => [
                    ['amount' => ['value' => '5.00', 'currency' => 'EUR']],
                    ['amount' => ['value' => '2.50', 'currency' => 'EUR']],
                ],
            ],
        ];

        $payment = (new PaymentHydrator())->hydrate($body);

        $this->assertSame(7.5, $payment->getVoucherAmount());
    }

    public function testRoundingDiffIsReadFromMetadata(): void
    {
        $body = [
            'id' => 'tr_test',
            'status' => PaymentStatus::PAID->value,
            'lines' => [
                [
                    'name' => RoundingDifferenceFixer::DEFAULT_TITLE,
                    'totalAmount' => ['value' => '0.01', 'currency' => 'EUR'],
                    'metadata' => ['type' => RoundingDifferenceFixer::METADATA_TYPE],
                ],
            ],
        ];

        $payment = (new PaymentHydrator())->hydrate($body);

        $this->assertSame(0.01, $payment->getRoundingDiff());
    }

    public function testRoundingDiffIsReadFromSku(): void
    {
        $body = [
            'id' => 'tr_test',
            'status' => PaymentStatus::PAID->value,
            'lines' => [
                [
                    'name' => RoundingDifferenceFixer::DEFAULT_TITLE,
                    'sku' => RoundingDifferenceFixer::SKU,
                    'totalAmount' => ['value' => '0.01', 'currency' => 'EUR'],
                ],
            ],
        ];

        $payment = (new PaymentHydrator())->hydrate($body);

        $this->assertSame(0.01, $payment->getRoundingDiff());
    }

    public function testRefundedCurrencyFallsBackToAmountCurrency(): void
    {
        $body = [
            'id' => 'tr_test',
            'status' => PaymentStatus::PAID->value,
            'amount' => ['value' => '10.00', 'currency' => 'EUR'],
            'amountRefunded' => ['value' => '4.00'],
        ];

        $payment = (new PaymentHydrator())->hydrate($body);

        $this->assertSame(4.0, $payment->getAmountRefunded()->getValue());
        $this->assertSame('EUR', $payment->getAmountRefunded()->getCurrency());
    }

    public function testChargebackIsDerivedAndTakesPrecedence(): void
    {
        $body = [
            'id' => 'tr_test',
            'status' => PaymentStatus::PAID->value,
            'amount' => ['value' => '10.00', 'currency' => 'EUR'],
            'amountRefunded' => ['value' => '4.00', 'currency' => 'EUR'],
            'amountRemaining' => ['value' => '0.00', 'currency' => 'EUR'],
            'amountChargedBack' => ['value' => '6.00', 'currency' => 'EUR'],
        ];

        $payment = (new PaymentHydrator())->hydrate($body);

        $this->assertTrue($payment->hasChargeback());
        $this->assertSame(PaymentStatus::CHARGEBACK, $payment->getStatus());
    }

    public function testFullRefundIsDerivedFromAmountRemaining(): void
    {
        $body = [
            'id' => 'tr_test',
            'status' => PaymentStatus::PAID->value,
            'amount' => ['value' => '10.00', 'currency' => 'EUR'],
            'amountRefunded' => ['value' => '10.00', 'currency' => 'EUR'],
            'amountRemaining' => ['value' => '0.00', 'currency' => 'EUR'],
        ];

        $payment = (new PaymentHydrator())->hydrate($body);

        $this->assertTrue($payment->isFullyRefunded());
        $this->assertSame(PaymentStatus::REFUNDED, $payment->getStatus());
    }

    public function testEmbeddedRefundsAreHydrated(): void
    {
        $body = [
            'id' => 'tr_test',
            'status' => PaymentStatus::PAID->value,
            '_embedded' => [
                'refunds' => [
                    [
                        'id' => 're_1',
                        'paymentId' => 'tr_test',
                        'status' => 'refunded',
                        'amount' => ['value' => '4.00', 'currency' => 'EUR'],
                        'createdAt' => '2026-07-23T10:00:00+00:00',
                    ],
                ],
            ],
        ];

        $payment = (new PaymentHydrator())->hydrate($body);

        $this->assertSame(4.0, $payment->getRefunds()->getSumRefunded());
    }
}
