<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi\Builder;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;

class MollieOrderAddressBuilder
{
    public const MOLLIE_DEFAULT_COUNTRY_ISO = 'NL';

    /**
     * @param string $email
     * @param null|CustomerAddressEntity $address
     * @return array<mixed>
     */
    public function build(string $email, ?CustomerAddressEntity $address): array
    {
        if (!$address instanceof CustomerAddressEntity) {
            return [];
        }

        $data = [
            'title' => ($address->getSalutation() !== null) ? trim((string)$address->getSalutation()->getDisplayName()) : null,
            'givenName' => $address->getFirstName(),
            'familyName' => $address->getLastName(),
            'email' => $email,
            'streetAndNumber' => $address->getStreet(),
            'postalCode' => $address->getZipCode(),
            'city' => $address->getCity(),
            'country' => $address->getCountry() !== null ? $address->getCountry()->getIso() : self::MOLLIE_DEFAULT_COUNTRY_ISO,
        ];

        $streetAdditional = trim((string)$address->getAdditionalAddressLine1());
        if (!empty($streetAdditional)) {
            $data['streetAdditional'] = $streetAdditional;
        }

        $company = trim((string)$address->getCompany());
        if (!empty($company)) {
            $data['organizationName'] = $company;
        }

        return $data;
    }
}
