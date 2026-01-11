<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Struct\LineItem;

use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

class LineItemAttributesTest extends TestCase
{
    /**
     * This test is very important.
     * It's used for the CartBeforeSerializationEvent event.
     * By providing our list of custom mollie fields as whitelist, we ensure that
     * the custom fields are still available in cart.lineItem.payload.customFields.
     * Otherwise they are removed in Shopware >= 6.5.
     */
    public function testKeyList()
    {
        $expected = [
            'mollie_payments_product_subscription_enabled',
            'mollie_payments_product_subscription_interval',
            'mollie_payments_product_subscription_interval_unit',
            'mollie_payments_product_subscription_repetition',
            'mollie_payments_product_subscription_repetition_type',
        ];

        $this->assertEquals($expected, LineItemAttributes::getKeyList());
    }

    /**
     * This test verifies that our product number from
     * the payload is correctly read.
     */
    public function testProductNumber()
    {
        $item = new LineItem('', '');
        $item->setPayload(['productNumber' => 'abc']);

        $attributes = new LineItemAttributes($item);

        $this->assertEquals('abc', $attributes->getProductNumber());
    }

    /**
     * This test verifies that nothing breaks if we
     * have NULL instead of a payload array.
     */
    public function testProductNumberNotExisting()
    {
        $item = new LineItem('', '');
        $item->setPayload(['otherData' => 'test']);

        $attributes = new LineItemAttributes($item);

        $this->assertEquals('', $attributes->getProductNumber());
    }
}
