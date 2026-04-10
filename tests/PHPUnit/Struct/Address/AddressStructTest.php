<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Struct\Address;

use Kiener\MolliePayments\Struct\Address\AddressStruct;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Kiener\MolliePayments\Struct\Address\AddressStruct
 */
class AddressStructTest extends TestCase
{
    public function testCreateFromApiResponseWithGivenNameAndFamilyName(): void
    {
        $address = new \stdClass();
        $address->givenName = 'John';
        $address->familyName = 'Doe';
        $address->email = 'john@example.com';
        $address->streetAndNumber = 'Main Street 1';
        $address->postalCode = '12345';
        $address->city = 'Berlin';
        $address->country = 'DE';
        $address->phone = '+4900000';

        $struct = AddressStruct::createFromApiResponse($address);

        $this->assertSame('John', $struct->getFirstName());
        $this->assertSame('Doe', $struct->getLastName());
    }

    public function testCreateFromApiResponseSplitsFullNameWhenOnlyFamilyNamePresent(): void
    {
        $address = new \stdClass();
        $address->familyName = 'John Doe';
        $address->email = 'john@example.com';
        $address->streetAndNumber = 'Main Street 1';
        $address->postalCode = '12345';
        $address->city = 'Berlin';
        $address->country = 'DE';
        $address->phone = '+4900000';

        $struct = AddressStruct::createFromApiResponse($address);

        $this->assertSame('John', $struct->getFirstName());
        $this->assertSame('Doe', $struct->getLastName());
    }

    /**
     * When PayPal returns only a single-word name in familyName, Shopware's required
     * firstName validation must not fail. We fall back to using the name for both fields.
     */
    public function testCreateFromApiResponseHandlesSingleWordFamilyName(): void
    {
        $address = new \stdClass();
        $address->familyName = 'Smith';
        $address->email = 'smith@example.com';
        $address->streetAndNumber = 'Main Street 1';
        $address->postalCode = '12345';
        $address->city = 'Berlin';
        $address->country = 'DE';
        $address->phone = '+4900000';

        $struct = AddressStruct::createFromApiResponse($address);

        $this->assertNotEmpty($struct->getFirstName(), 'firstName must not be empty to pass Shopware validation');
        $this->assertNotEmpty($struct->getLastName(), 'lastName must not be empty');
        $this->assertSame('Smith', $struct->getFirstName());
        $this->assertSame('Smith', $struct->getLastName());
    }

    public function testCreateFromApiResponseDoesNotOverwriteExistingGivenName(): void
    {
        $address = new \stdClass();
        $address->givenName = 'Jane';
        $address->familyName = 'Doe';
        $address->email = 'jane@example.com';
        $address->streetAndNumber = 'Side Street 5';
        $address->postalCode = '54321';
        $address->city = 'Hamburg';
        $address->country = 'DE';
        $address->phone = '+4911111';

        $struct = AddressStruct::createFromApiResponse($address);

        $this->assertSame('Jane', $struct->getFirstName());
        $this->assertSame('Doe', $struct->getLastName());
    }
}
