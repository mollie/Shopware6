<?php declare(strict_types=1);

namespace MolliePayments\Tests\Struct\LineItem;

use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

class LineItemAttributesTest extends TestCase
{

    /**
     *
     */
    public function testEmptyPayload()
    {
        $item = new LineItem('', '');

        $attributes = new LineItemAttributes($item);

        $this->assertEquals('', $attributes->getVoucherType());
    }

    /**
     *
     */
    public function testEmptyCustomFields()
    {
        $item = new LineItem('', '');
        $item->setPayload([
            'customFields' => []
        ]);

        $attributes = new LineItemAttributes($item);

        $this->assertEquals('', $attributes->getVoucherType());
    }

    /**
     *
     */
    public function testEmptyMolliePayments()
    {
        $item = new LineItem('', '');
        $item->setPayload([
            'customFields' => [
                'mollie_payments' => [

                ]
            ]
        ]);

        $attributes = new LineItemAttributes($item);

        $this->assertEquals('', $attributes->getVoucherType());
    }

    /**
     *
     */
    public function testVoucherType()
    {
        $item = new LineItem('', '');
        $item->setPayload([
            'customFields' => [
                'mollie_payments' => [
                    'voucher_type' => '2',
                ]
            ]
        ]);

        $attributes = new LineItemAttributes($item);

        $this->assertEquals('2', $attributes->getVoucherType());
    }

}
