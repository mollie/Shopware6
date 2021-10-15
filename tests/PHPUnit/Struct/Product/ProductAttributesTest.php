<?php

namespace MolliePayments\Tests\Struct\LineItem;

use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Kiener\MolliePayments\Struct\Product\ProductAttributes;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Product\ProductEntity;


class ProductAttributesTest extends TestCase
{

    /**
     *
     */
    public function testEmptyCustomFields()
    {
        $method = new ProductEntity();
        $method->setCustomFields([]);

        $attributes = new ProductAttributes($method);

        $this->assertEquals('', $attributes->getVoucherType());
    }

    /**
     *
     */
    public function testVoucherType()
    {
        $method = new ProductEntity();
        $method->setCustomFields([
            'mollie_payments' => [
                'voucher_type' => '2'
            ]
        ]);

        $attributes = new ProductAttributes($method);

        $this->assertEquals('2', $attributes->getVoucherType());
    }

}
