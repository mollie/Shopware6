<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Entity\PaymentMethod;

use Mollie\Shopware\Component\Mollie\PaymentMethod as MolliePaymentMethod;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentMethod::class)]
final class PaymentMethodTest extends TestCase
{
    public function testGetters(): void
    {
        $paymentMethod = new PaymentMethod('pm-123', MolliePaymentMethod::IDEAL);

        $this->assertSame('pm-123', $paymentMethod->getId());
        $this->assertSame(MolliePaymentMethod::IDEAL, $paymentMethod->getPaymentMethod());
    }

    public function testDifferentPaymentMethods(): void
    {
        $creditCard = new PaymentMethod('pm-cc', MolliePaymentMethod::CREDIT_CARD);
        $paypal = new PaymentMethod('pm-pp', MolliePaymentMethod::PAYPAL);

        $this->assertSame(MolliePaymentMethod::CREDIT_CARD, $creditCard->getPaymentMethod());
        $this->assertSame(MolliePaymentMethod::PAYPAL, $paypal->getPaymentMethod());
        $this->assertNotSame($creditCard->getId(), $paypal->getId());
    }
}
