<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Builder;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;

final class SubscriptionAddressBuilder
{
    private string $id = 'subscription-address-id';
    private string $subscriptionId = 'subscription-id';
    private ?string $salutationId = 'salutation-id';
    private string $firstName = 'Test';
    private string $lastName = 'Customer';
    private ?string $company = null;
    private ?string $department = null;
    private string $street = 'Default Street 1';
    private string $zipcode = '12345';
    private string $city = 'Berlin';
    private string $countryId = 'country-id';
    private ?string $countryStateId = null;
    private ?string $phoneNumber = null;
    private ?string $additionalAddressLine1 = null;
    private ?string $additionalAddressLine2 = null;

    public static function create(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function withSubscriptionId(string $subscriptionId): self
    {
        $this->subscriptionId = $subscriptionId;

        return $this;
    }

    public function withFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function withLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function withStreet(string $street): self
    {
        $this->street = $street;

        return $this;
    }

    public function withZipcode(string $zipcode): self
    {
        $this->zipcode = $zipcode;

        return $this;
    }

    public function withCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function withCountryId(string $countryId): self
    {
        $this->countryId = $countryId;

        return $this;
    }

    public function build(): SubscriptionAddressEntity
    {
        $address = new SubscriptionAddressEntity();
        $address->setId($this->id);
        $address->setSubscriptionId($this->subscriptionId);
        $address->setSalutationId($this->salutationId);
        $address->setFirstName($this->firstName);
        $address->setLastName($this->lastName);
        $address->setCompany($this->company);
        $address->setDepartment($this->department);
        $address->setStreet($this->street);
        $address->setZipcode($this->zipcode);
        $address->setCity($this->city);
        $address->setCountryId($this->countryId);
        $address->setCountryStateId($this->countryStateId);
        $address->setPhoneNumber($this->phoneNumber);
        $address->setAdditionalAddressLine1($this->additionalAddressLine1);
        $address->setAdditionalAddressLine2($this->additionalAddressLine2);

        return $address;
    }
}
