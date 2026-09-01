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

        self::assertSame('salutation-id', $data->salutationId);
        self::assertSame('Dr.', $data->title);
        self::assertSame('Jane', $data->firstName);
        self::assertSame('Doe', $data->lastName);
        self::assertSame('ACME', $data->company);
        self::assertSame('Purchasing', $data->department);
        self::assertSame('+4915112345678', $data->phoneNumber);
        self::assertSame('Main 1', $data->street);
        self::assertSame('12345', $data->zipcode);
        self::assertSame('Berlin', $data->city);
        self::assertSame('country-id', $data->countryId);
        self::assertSame('state-id', $data->countryStateId);
        self::assertSame('c/o Doe', $data->additionalAddressLine1);
        self::assertSame('Backyard', $data->additionalAddressLine2);
    }

    public function testTheStorefrontFormMayNestTheFieldsUnderAddress(): void
    {
        $data = UpdateAddressData::fromRequestData(new RequestDataBag([
            'address' => new RequestDataBag(['firstName' => 'Jane', 'city' => 'Berlin']),
        ]));

        self::assertSame('Jane', $data->firstName);
        self::assertSame('Berlin', $data->city);
    }

    public function testAnEmptyRequestProducesAnEmptyAddress(): void
    {
        $data = UpdateAddressData::fromRequestData(new RequestDataBag());

        self::assertSame('', $data->salutationId);
        self::assertSame('', $data->firstName);
        self::assertSame('', $data->countryId);
    }

    #[DataProvider('optionalFields')]
    public function testAnOmittedOptionalFieldStaysEmpty(string $field, string $property): void
    {
        $data = UpdateAddressData::fromRequestData(new RequestDataBag());

        self::assertNull($data->{$property}, sprintf('%s should not be sent to Shopware', $field));
    }

    #[DataProvider('optionalFields')]
    public function testAnOptionalFieldTheShopperClearedStaysEmpty(string $field, string $property): void
    {
        // Shopware would store an empty string as a real value, so an emptied input has to become null.
        $data = UpdateAddressData::fromRequestData(new RequestDataBag([$field => '']));

        self::assertNull($data->{$property});
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

        self::assertSame('aabbcc', $data->salutationId);
        self::assertSame('ddeeff', $data->countryId);
        self::assertSame('112233aa', $data->countryStateId);
    }
}
