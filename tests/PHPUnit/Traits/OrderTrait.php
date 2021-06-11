<?php declare(strict_types=1);


namespace MolliePayments\Tests\Traits;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

trait OrderTrait
{
    public function getCustomerAddressEntity(
        string $firstName,
        string $lastName,
        string $street,
        string $zipCode,
        string $city,
        ?string $salutationName,
        ?string $countryISO,
        ?string $additional): CustomerAddressEntity
    {
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setId(Uuid::randomHex());

        if (!empty($salutationName)) {
            $salutation = new SalutationEntity();
            $salutation->setId(Uuid::randomHex());
            $salutation->setDisplayName($salutationName);
            $customerAddress->setSalutation($salutation);
        }

        $customerAddress->setFirstName($firstName);
        $customerAddress->setLastName($lastName);
        $customerAddress->setStreet($street);
        if (!empty($additional)) {
            $customerAddress->setAdditionalAddressLine1($additional);
        }

        $customerAddress->setZipcode($zipCode);
        $customerAddress->setCity($city);

        if (!empty($countryISO)) {
            $country = new CountryEntity();
            $country->setId(Uuid::randomHex());
            $country->setIso($countryISO);
            $customerAddress->setCountry($country);
        }

        return $customerAddress;
    }
}
