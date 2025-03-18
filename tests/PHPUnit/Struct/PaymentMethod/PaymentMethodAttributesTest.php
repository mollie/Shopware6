<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Struct\PaymentMethod;

use Kiener\MolliePayments\Handler\Method\PayPalPayment;
use Kiener\MolliePayments\Handler\Method\VoucherPayment;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;

class PaymentMethodAttributesTest extends TestCase
{
    /**
     * This test verifies that our VoucherPayment class is
     * recognized as "voucher" payment type.
     */
    public function testVoucherIsDetected()
    {
        $method = new PaymentMethodEntity();
        $method->setHandlerIdentifier(VoucherPayment::class);

        $attributes = new PaymentMethodAttributes($method);

        $this->assertEquals(true, $attributes->isVoucherMethod());
    }

    /**
     * This test verifies that a non-voucher payment, such as
     * PayPal is not accidentally recognized as "voucher" payment.
     */
    public function testPaypalIsNoVoucher()
    {
        $method = new PaymentMethodEntity();
        $method->setHandlerIdentifier(PayPalPayment::class);

        $attributes = new PaymentMethodAttributes($method);

        $this->assertEquals(false, $attributes->isVoucherMethod());
    }
}
