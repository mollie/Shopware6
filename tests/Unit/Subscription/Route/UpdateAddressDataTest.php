<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Route;

use Mollie\Shopware\Component\Subscription\Route\UpdateAddressData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

#[CoversClass(UpdateAddressData::class)]
final class UpdateAddressDataTest extends TestCase
{
    public function testEveryAddressFieldIsReadFromTheRequest(): void
    {
        $data = UpdateAddressData::fromRequestData(new RequestDataBag([
            'salutationId' => 'salutation-id',
            'title' => 'Dr.',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'company' => 'ACME',
            'department' => 'Purchasing',
            'phoneNumber' => '+4915112345678',
            'street' => 'Main 1',
            'zipcode' => '12345',
            'city' => 'Berlin',
            'countryId' => 'country-id',
            'countryStateId' => 'state-id',
            'additionalField1' => 'c/o Doe',
            'additionalField2' => 'Backyard',
        ]));

        static::assertSame('salutation-id', $data->salutationId);
        static::assertSame('Dr.', $data->title);
        static::assertSame('Jane', $data->firstName);
        static::assertSame('Doe', $data->lastName);
        static::assertSame('ACME', $data->company);
        static::assertSame('Purchasing', $data->department);
        static::assertSame('+4915112345678', $data->phoneNumber);
        static::assertSame('Main 1', $data->street);
        static::assertSame('12345', $data->zipcode);
        static::assertSame('Berlin', $data->city);
        static::assertSame('country-id', $data->countryId);
        static::assertSame('state-id', $data->countryStateId);
        static::assertSame('c/o Doe', $data->additionalAddressLine1);
        static::assertSame('Backyard', $data->additionalAddressLine2);
    }

    public function testTheStorefrontFormMayNestTheFieldsUnderAddress(): void
    {
        $data = UpdateAddressData::fromRequestData(new RequestDataBag([
            'address' => new RequestDataBag(['firstName' => 'Jane', 'city' => 'Berlin']),
        ]));

        static::assertSame('Jane', $data->firstName);
        static::assertSame('Berlin', $data->city);
    }

    public function testAnEmptyRequestProducesAnEmptyAddress(): void
    {
        $data = UpdateAddressData::fromRequestData(new RequestDataBag());

        static::assertSame('', $data->salutationId);
        static::assertSame('', $data->firstName);
        static::assertSame('', $data->countryId);
    }

    #[DataProvider('optionalFields')]
    public function testAnOmittedOptionalFieldStaysEmpty(string $field, string $property): void
    {
        $data = UpdateAddressData::fromRequestData(new RequestDataBag());

        static::assertNull($data->{$property}, sprintf('%s should not be sent to Shopware', $field));
    }

    #[DataProvider('optionalFields')]
    public function testAnOptionalFieldTheShopperClearedStaysEmpty(string $field, string $property): void
    {
        // Shopware would store an empty string as a real value, so an emptied input has to become null.
        $data = UpdateAddressData::fromRequestData(new RequestDataBag([$field => '']));

        static::assertNull($data->{$property});
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function optionalFields(): array
    {
        return [
            'title' => ['title', 'title'],
            'company' => ['company', 'company'],
            'department' => ['department', 'department'],
            'phone number' => ['phoneNumber', 'phoneNumber'],
            'country state' => ['countryStateId', 'countryStateId'],
            'additional line 1' => ['additionalField1', 'additionalAddressLine1'],
            'additional line 2' => ['additionalField2', 'additionalAddressLine2'],
        ];
    }

    public function testIdsAreLoweredSoTheyMatchTheStoredUuids(): void
    {
        $data = UpdateAddressData::fromRequestData(new RequestDataBag([
            'salutationId' => 'AABBCC',
            'countryId' => 'DDEEFF',
            'countryStateId' => '112233AA',
        ]));

        static::assertSame('aabbcc', $data->salutationId);
        static::assertSame('ddeeff', $data->countryId);
        static::assertSame('112233aa', $data->countryStateId);
    }
}
