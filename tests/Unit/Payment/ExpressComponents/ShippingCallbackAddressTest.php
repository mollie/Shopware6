<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\ShippingCallbackAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShippingCallbackAddress::class)]
final class ShippingCallbackAddressTest extends TestCase
{
    public function testCreateFromCallbackBody(): void
    {
        $address = ShippingCallbackAddress::fromArray([
            'postalCode' => '1015 CW',
            'city' => 'Amsterdam',
            'region' => 'Noord-Holland',
            'country' => 'NL',
        ]);

        $this->assertSame('NL', $address->getCountry());
        $this->assertSame('1015 CW', $address->getPostalCode());
        $this->assertSame('Amsterdam', $address->getCity());
        $this->assertSame('Noord-Holland', $address->getRegion());
    }

    public function testMissingFieldsBecomeEmptyStrings(): void
    {
        $address = ShippingCallbackAddress::fromArray([]);

        $this->assertSame('', $address->getCountry());
        $this->assertSame('', $address->getPostalCode());
        $this->assertSame('', $address->getCity());
        $this->assertSame('', $address->getRegion());
    }
}
