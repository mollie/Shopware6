<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi\Builder;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;

class MollieOrderAddressBuilder
{
    public const MOLLIE_DEFAULT_COUNTRY_ISO = 'NL';

    /**
     * @param null|CustomerAddressEntity|OrderAddressEntity $address
     *
     * @return array<mixed>
     */
    public function build(string $email, $address): array
    {
        if (! $address instanceof OrderAddressEntity && ! $address instanceof CustomerAddressEntity) {
            return [];
        }

        $data = [
            'title' => ($address->getSalutation() !== null) ? trim((string) $address->getSalutation()->getDisplayName()) : null,
            'givenName' => $address->getFirstName(),
            'familyName' => $address->getLastName(),
            'email' => $email,
            'streetAndNumber' => $address->getStreet(),
            'postalCode' => $address->getZipCode(),
            'city' => $address->getCity(),
            'country' => $address->getCountry() !== null ? $address->getCountry()->getIso() : self::MOLLIE_DEFAULT_COUNTRY_ISO,
        ];

        $streetAdditional = trim((string) $address->getAdditionalAddressLine1());
        if (! empty($streetAdditional)) {
            $data['streetAdditional'] = $streetAdditional;
        }

        $company = trim((string) $address->getCompany());
        if (! empty($company)) {
            $data['organizationName'] = $company;
        }

        return $data;
    }
}
