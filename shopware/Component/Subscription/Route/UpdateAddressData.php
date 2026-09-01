<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Route;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

final class UpdateAddressData
{
    public function __construct(
        public readonly string $salutationId,
        public readonly ?string $title,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $company,
        public readonly ?string $department,
        public readonly ?string $phoneNumber,
        public readonly string $street,
        public readonly string $zipcode,
        public readonly string $city,
        public readonly string $countryId,
        public readonly ?string $countryStateId,
        public readonly ?string $additionalAddressLine1,
        public readonly ?string $additionalAddressLine2,
    ) {
    }

    public static function fromRequestData(RequestDataBag $data): self
    {
        $address = $data->get('address');
        if ($address instanceof RequestDataBag) {
            $data = $address;
        }

        return new self(
            salutationId: self::normaliseId((string) $data->get('salutationId', '')),
            title: self::nullableString($data->get('title')),
            firstName: (string) $data->get('firstName', ''),
            lastName: (string) $data->get('lastName', ''),
            company: self::nullableString($data->get('company')),
            department: self::nullableString($data->get('department')),
            phoneNumber: self::nullableString($data->get('phoneNumber')),
            street: (string) $data->get('street', ''),
            zipcode: (string) $data->get('zipcode', ''),
            city: (string) $data->get('city', ''),
            countryId: self::normaliseId((string) $data->get('countryId', '')),
            countryStateId: self::nullableId($data->get('countryStateId')),
            additionalAddressLine1: self::nullableString($data->get('additionalField1')),
            additionalAddressLine2: self::nullableString($data->get('additionalField2')),
        );
    }

    private static function normaliseId(string $value): string
    {
        return strtolower($value);
    }

    private static function nullableId(mixed $value): ?string
    {
        $value = self::nullableString($value);

        return $value === null ? null : strtolower($value);
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = (string) $value;

        return $value === '' ? null : $value;
    }
}
