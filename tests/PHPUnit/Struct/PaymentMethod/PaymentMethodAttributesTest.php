<?php

namespace MolliePayments\Tests\Struct\LineItem;

use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;


class PaymentMethodAttributesTest extends TestCase
{

    /**
     *
     */
    public function testEmptyCustomFields()
    {
        $method = new PaymentMethodEntity();
        $method->setCustomFields([]);

        $attributes = new PaymentMethodAttributes($method);

        $this->assertEquals('', $attributes->getMolliePaymentName());
    }

    /**
     *
     */
    public function testMolliePaymentName()
    {
        $method = new PaymentMethodEntity();
        $method->setCustomFields([
            'mollie_payment_method_name' => 'voucher',
        ]);

        $attributes = new PaymentMethodAttributes($method);

        $this->assertEquals('voucher', $attributes->getMolliePaymentName());
    }

}
