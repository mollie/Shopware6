<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Entity\PaymentMethod;

use Mollie\Shopware\Component\Mollie\PaymentMethod as MolliePaymentMethod;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The extension is what tells the checkout which Mollie method a Shopware payment method stands
 * for, and which Shopware method a Mollie answer belongs to.
 */
#[CoversClass(PaymentMethod::class)]
final class PaymentMethodTest extends TestCase
{
    public function testTheExtensionKnowsWhichMollieMethodTheShopwareMethodStandsFor(): void
    {
        $extension = new PaymentMethod('payment-method-1', MolliePaymentMethod::IDEAL);

        $this->assertSame(MolliePaymentMethod::IDEAL, $extension->getPaymentMethod());
    }

    public function testTheExtensionKeepsTheShopwarePaymentMethodId(): void
    {
        $extension = new PaymentMethod('payment-method-1', MolliePaymentMethod::IDEAL);

        $this->assertSame('payment-method-1', $extension->getId());
    }
}
