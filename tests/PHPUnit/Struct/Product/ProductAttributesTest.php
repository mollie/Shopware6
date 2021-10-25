<?php

namespace MolliePayments\Tests\Struct\LineItem;

use Kiener\MolliePayments\Struct\Product\ProductAttributes;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;


class ProductAttributesTest extends TestCase
{


    /**
     * This test verifies that non-existing custom fields
     * are correctly handled without an error.
     */
    public function testEmptyCustomFields()
    {
        $method = new ProductEntity();
        $method->setCustomFields([]);

        $attributes = new ProductAttributes($method);

        $this->assertEquals('', $attributes->getVoucherType());
    }

    /**
     * This test verifies that a valid voucher type
     * is correctly assigned and returned later on.
     */
    public function testVoucherType()
    {
        $method = new ProductEntity();
        $method->setCustomFields([
            'mollie_payments' => [
                'voucher_type' => VoucherType::TYPE_MEAL
            ]
        ]);

        $attributes = new ProductAttributes($method);

        $this->assertEquals(VoucherType::TYPE_MEAL, $attributes->getVoucherType());
    }

    /**
     * This test verifies that we get a NOT SET value
     * for every type that is not officially known.
     */
    public function testUnknownVoucherType()
    {
        $method = new ProductEntity();
        $method->setCustomFields([
            'mollie_payments' => [
                'voucher_type' => '5'
            ]
        ]);

        $attributes = new ProductAttributes($method);

        $this->assertEquals(VoucherType::TYPE_NOTSET, $attributes->getVoucherType());
    }

}
