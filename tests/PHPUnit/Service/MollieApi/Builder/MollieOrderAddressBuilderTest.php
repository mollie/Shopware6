<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi\Builder;

use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderAddressBuilder;
use MolliePayments\Tests\Traits\OrderTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;

class MollieOrderAddressBuilderTest extends TestCase
{
    use OrderTrait;

    /**
     * @var MollieOrderAddressBuilder
     */
    private $builder;

    protected function setUp(): void
    {
        $this->builder = new MollieOrderAddressBuilder();
    }

    public function testConstants(): void
    {
        self::assertSame('NL', MollieOrderAddressBuilder::MOLLIE_DEFAULT_COUNTRY_ISO);
    }

    public function testBuildWithNullAddress(): void
    {
        self::assertSame([], $this->builder->build('foo', null));
    }

    /**
     * This test verifies that our address data for Mollie
     * can be built correctly.
     */
    public function testBuild(): void
    {
        $orderAddress = $this->buildFixture('Mr', 'DE', 'great street');

        $addressData = $this->builder->build('test@mollie.com', $orderAddress);

        $expected = [
            'title' => 'Mr',
            'givenName' => 'Mollie',
            'familyName' => 'HQ',
            'email' => 'test@mollie.com',
            'streetAndNumber' => 'Keizersgracht 126',
            'postalCode' => '1015 CW',
            'city' => 'Amsterdam',
            'country' => 'DE',
            'streetAdditional' => 'great street',
        ];

        self::assertSame($expected, $addressData);
    }

    /**
     * This test verifies that the value of a missing salutation
     * leads to a title with NULL as value.
     */
    public function testBuildWithMissingSalutation(): void
    {
        $orderAddress = $this->buildFixture(null, 'DE', '');

        $addressData = $this->builder->build('test@mollie.com', $orderAddress);

        self::assertNull($addressData['title']);
    }

    /**
     * This test verifies that the value of a salutation (title in Mollies API)
     * will never contain whitespace only.
     */
    public function testBuildWithWhitespaceSalutation(): void
    {
        $orderAddress = $this->buildFixture('  ', 'DE', '');

        $addressData = $this->builder->build('test@mollie.com', $orderAddress);

        self::assertEmpty($addressData['title']);
    }

    /**
     * This test verifies that the country should have NL as default value
     * if no ISO2 code has been provided (null)
     */
    public function testBuildWithMissingCountry(): void
    {
        $orderAddress = $this->buildFixture('Mr', null, '');

        $addressData = $this->builder->build('test@mollie.com', $orderAddress);

        self::assertSame(MollieOrderAddressBuilder::MOLLIE_DEFAULT_COUNTRY_ISO, $addressData['country']);
    }

    /**
     * This test verifies that a missing additional street is not even
     * added to the array (Mollie doesn't allow this)
     */
    public function testBuildWithMissingAdditionalStreetNull(): void
    {
        $orderAddress = $this->buildFixture('Mr', 'DE', null);

        $addressData = $this->builder->build('test@mollie.com', $orderAddress);

        self::assertArrayNotHasKey('streetAdditional', $addressData);
    }

    /**
     * This test verifies that an empty additional street with only whitespaces is not even
     * added to the array (Mollie doesn't allow this)
     */
    public function testBuildWithMissingAdditionalStreetSpace(): void
    {
        $additional = ' ';
        $orderAddress = $this->buildFixture('Mr', 'DE', $additional);

        $addressData = $this->builder->build('test@mollie.com', $orderAddress);

        self::assertArrayNotHasKey('streetAdditional', $addressData);
    }

    private function buildFixture(?string $salutation, ?string $countryISO, ?string $additional): CustomerAddressEntity
    {
        $firstName = 'Mollie';
        $lastName = 'HQ';
        $street = 'Keizersgracht 126';
        $zip = '1015 CW';
        $city = 'Amsterdam';

        return $this->getOrderAddressEntity(
            $firstName,
            $lastName,
            $street,
            $zip,
            $city,
            $salutation,
            $countryISO,
            $additional
        );
    }
}
