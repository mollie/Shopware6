<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi\Builder;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;

class MollieOrderAddressBuilder
{
    public const MOLLIE_DEFAULT_COUNTRY_ISO = 'NL';

    public function build(string $email, ?CustomerAddressEntity $address): array
    {
        if (!$address instanceof CustomerAddressEntity) {
            return [];
        }

        return [
            'title' => $address->getSalutation() !== null ? $address->getSalutation()->getDisplayName() : null,
            'givenName' => $address->getFirstName(),
            'familyName' => $address->getLastName(),
            'email' => $email,
            'streetAndNumber' => $address->getStreet(),
            'streetAdditional' => $address->getAdditionalAddressLine1(),
            'postalCode' => $address->getZipCode(),
            'city' => $address->getCity(),
            'country' => $address->getCountry() !== null ? $address->getCountry()->getIso() : self::MOLLIE_DEFAULT_COUNTRY_ISO,
        ];
    }
}
