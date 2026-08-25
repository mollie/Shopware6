<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\ShippingOption;
use Mollie\Shopware\Component\Mollie\ShippingOptionCollection;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\ShippingOptionsResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShippingOptionsResponse::class)]
final class ShippingOptionsResponseTest extends TestCase
{
    public function testStoreApiPayloadListsEveryShippingOption(): void
    {
        $options = new ShippingOptionCollection([
            new ShippingOption('Standard', 'shipping-method-1', new Money(4.99, 'EUR')),
            new ShippingOption('Express', 'shipping-method-2', new Money(9.99, 'EUR')),
        ]);

        $response = new ShippingOptionsResponse($options);

        $this->assertCount(2, $response->getObject()->all()['shippingOptions']);
    }

    public function testShopWithoutShippingOptionsReturnsAnEmptyList(): void
    {
        $response = new ShippingOptionsResponse(new ShippingOptionCollection([]));

        $this->assertSame(['shippingOptions' => []], $response->getObject()->all());
    }

    public function testApiAliasIdentifiesTheShippingOptionsResponse(): void
    {
        $response = new ShippingOptionsResponse(new ShippingOptionCollection([]));

        $this->assertSame('express_components_shipping_options_response', $response->getObject()->getApiAlias());
    }
}
