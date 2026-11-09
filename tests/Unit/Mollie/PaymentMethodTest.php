<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentMethod::class)]
final class PaymentMethodTest extends TestCase
{
    public function testCardMapsToBankCardCode(): void
    {
        $this->assertSame(48, PaymentMethod::CREDIT_CARD->eInvoicePaymentMeansCode());
    }

    public function testBankTransferMapsToSepaCreditTransferCode(): void
    {
        $this->assertSame(58, PaymentMethod::BANK_TRANSFER->eInvoicePaymentMeansCode());
    }

    public function testDirectDebitMethodsMapToSepaDirectDebitCode(): void
    {
        $this->assertSame(59, PaymentMethod::DIRECT_DEBIT->eInvoicePaymentMeansCode());
        $this->assertSame(59, PaymentMethod::BACS->eInvoicePaymentMeansCode());
    }

    public function testEveryOtherMethodFallsBackToOnlinePaymentServiceCode(): void
    {
        $explicit = [
            PaymentMethod::CREDIT_CARD,
            PaymentMethod::BANK_TRANSFER,
            PaymentMethod::DIRECT_DEBIT,
            PaymentMethod::BACS,
        ];

        foreach (PaymentMethod::cases() as $method) {
            if (in_array($method, $explicit, true)) {
                continue;
            }

            $this->assertSame(
                68,
                $method->eInvoicePaymentMeansCode(),
                sprintf('Expected "%s" to fall back to code 68', $method->value)
            );
        }
    }
}
