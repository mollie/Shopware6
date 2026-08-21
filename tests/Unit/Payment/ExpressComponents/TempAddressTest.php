<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressMethod\TempAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(TempAddress::class)]
final class TempAddressTest extends TestCase
{
    public function testIdIsStableForTheSameCustomer(): void
    {
        $customer = $this->customer();

        $this->assertSame(TempAddress::getId($customer), TempAddress::getId($customer));
    }

    /**
     * Shopware rejects a blank city, so the placeholder has to win for both null and an empty
     * string - a wallet callback that knows no city sends the latter.
     */
    public function testCityFallsBackToThePlaceholder(): void
    {
        $countryId = Uuid::fromStringToHex('country');

        $withoutCity = (new TempAddress($this->customer(), $countryId))->toUpsertArray();
        $withEmptyCity = (new TempAddress($this->customer(), $countryId, '', ''))->toUpsertArray();

        $this->assertSame('not provided', $withoutCity['city']);
        $this->assertSame('not provided', $withEmptyCity['city']);
        $this->assertSame('not provided', $withoutCity['street']);
    }

    public function testZipcodeIsOnlySentWhenKnown(): void
    {
        $countryId = Uuid::fromStringToHex('country');

        $this->assertArrayNotHasKey('zipcode', (new TempAddress($this->customer(), $countryId))->toUpsertArray());
        $this->assertArrayNotHasKey('zipcode', (new TempAddress($this->customer(), $countryId, 'Northeim', ''))->toUpsertArray());

        $address = (new TempAddress($this->customer(), $countryId, 'Northeim', '37154'))->toUpsertArray();
        $this->assertSame('37154', $address['zipcode']);
        $this->assertSame('Northeim', $address['city']);
    }

    private function customer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::fromStringToHex('customer'));
        $customer->setSalutationId(Uuid::fromStringToHex('salutation'));
        $customer->setFirstName('Max');
        $customer->setLastName('Mustermann');

        return $customer;
    }
}
