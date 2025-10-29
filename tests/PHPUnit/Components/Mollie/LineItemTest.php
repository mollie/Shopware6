<?php
declare(strict_types=1);

namespace Mollie\PHPUnit\Components\Mollie;

use Mollie\Shopware\Component\Mollie\LineItem;
use MolliePayments\Tests\Traits\OrderTrait;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

final class LineItemTest extends TestCase
{
    use OrderTrait;

    public function testCreateLineItemFromDelivery(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('123');
        $shippingMethod->setName('DHL Express');
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $orderDelivery = $this->getOrderDelivery('test', 0.87, 19, 4.99);
        $orderDelivery->setShippingMethod($shippingMethod);

        $actual = LineItem::fromDelivery($orderDelivery, $currency);

        $expected = [
            'type' => 'shipping_fee',
            'vatRate' => '19',
            'vatAmount' => [
                'currency' => 'EUR',
                'value' => '0.87',
            ],
            'sku' => 'mol-delivery-123',
            'description' => 'DHL Express',
            'quantity' => 1,
            'unitPrice' => [
                'currency' => 'EUR',
                'value' => '4.99',
            ],
            'totalAmount' => [
                'currency' => 'EUR',
                'value' => '4.99',
            ],
        ];

        Assert::assertInstanceOf(LineItem::class, $actual);
        Assert::assertEquals($expected, $actual->toArray());
    }
}
