<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi\Builder;

use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderAddressBuilder;
use MolliePayments\Tests\Traits\OrderTrait;
use PHPUnit\Framework\TestCase;

class MollieOrderAddressBuilderTest extends TestCase
{
    use OrderTrait;

    public function testConstants(): void
    {
        self::assertSame('NL', MollieOrderAddressBuilder::MOLLIE_DEFAULT_COUNTRY_ISO);
    }

    public function testBuildWithNullAddress(): void
    {
        self::assertSame([], (new MollieOrderAddressBuilder())->build('foo', null));
    }

    public function testBuild(): void
    {
        $salutation = 'Mr';
        $firstName = 'foo';
        $lastName = 'bar';
        $street = 'foostreet';
        $additional = 'additional';
        $zip = '12345';
        $city = 'city';
        $country = 'DE';

        $customerAddress = $this->getCustomerAddressEntity($firstName, $lastName, $street, $zip, $city, $salutation, $country, $additional);
        $email = 'baz';

        $expected = [
            'title' => $salutation,
            'givenName' => $firstName,
            'familyName' => $lastName,
            'email' => $email,
            'streetAndNumber' => $street,
            'streetAdditional' => $additional,
            'postalCode' => $zip,
            'city' => $city,
            'country' => $country,
        ];

        self::assertSame($expected, (new MollieOrderAddressBuilder())->build($email, $customerAddress));
    }

    public function testBuildWithMissingSalutation(): void
    {
        $salutation = null;
        $firstName = 'foo';
        $lastName = 'bar';
        $street = 'foostreet';
        $additional = 'additional';
        $zip = '12345';
        $city = 'city';
        $country = 'DE';

        $customerAddress = $this->getCustomerAddressEntity($firstName, $lastName, $street, $zip, $city, $salutation, $country, $additional);
        $email = 'baz';

        $expected = [
            'title' => $salutation,
            'givenName' => $firstName,
            'familyName' => $lastName,
            'email' => $email,
            'streetAndNumber' => $street,
            'streetAdditional' => $additional,
            'postalCode' => $zip,
            'city' => $city,
            'country' => $country,
        ];

        self::assertSame($expected, (new MollieOrderAddressBuilder())->build($email, $customerAddress));
    }

    public function testBuildWithMissingCountry(): void
    {
        $salutation = 'Mr';
        $firstName = 'foo';
        $lastName = 'bar';
        $street = 'foostreet';
        $additional = 'additional';
        $zip = '12345';
        $city = 'city';
        $country = null;

        $customerAddress = $this->getCustomerAddressEntity($firstName, $lastName, $street, $zip, $city, $salutation, $country, $additional);
        $email = 'baz';

        $expected = [
            'title' => $salutation,
            'givenName' => $firstName,
            'familyName' => $lastName,
            'email' => $email,
            'streetAndNumber' => $street,
            'streetAdditional' => $additional,
            'postalCode' => $zip,
            'city' => $city,
            'country' => MollieOrderAddressBuilder::MOLLIE_DEFAULT_COUNTRY_ISO,
        ];

        self::assertSame($expected, (new MollieOrderAddressBuilder())->build($email, $customerAddress));
    }
}
