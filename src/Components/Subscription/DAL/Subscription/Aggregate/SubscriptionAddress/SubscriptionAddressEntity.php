<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionDefinition;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

class SubscriptionAddressEntity extends Entity
{
    use EntityIdTrait;


    /**
     * @var string
     */
    protected $subscriptionId;

    /**
     * @var null|string
     */
    protected $salutationId;

    /**
     * @var null|string
     */
    protected $title;

    /**
     * @var string
     */
    protected $firstName;

    /**
     * @var string
     */
    protected $lastName;

    /**
     * @var null|string
     */
    protected $company;

    /**
     * @var null|string
     */
    protected $department;

    /**
     * @var null|string
     */
    protected $vatId;

    /**
     * @var string
     */
    protected $street;

    /**
     * @var string
     */
    protected $zipcode;

    /**
     * @var string
     */
    protected $city;

    /**
     * @var string
     */
    protected $countryId;

    /**
     * @var null|string
     */
    protected $countryStateId;

    /**
     * @var null|CountryEntity
     */
    protected $country;

    /**
     * @var null|CountryStateEntity
     */
    protected $countryState;

    /**
     * @var null|string
     */
    protected $phoneNumber;

    /**
     * @var null|string
     */
    protected $additionalAddressLine1;

    /**
     * @var null|string
     */
    protected $additionalAddressLine2;

    # --------------------------------------------------------------------------------
    # loaded entities

    /**
     * @var null|SubscriptionEntity
     */
    protected $subscription;

    /**
     * @var null|SalutationEntity
     */
    protected $salutation;

    /**
     * @var SubscriptionDefinition
     */
    protected $billingSubscription;

    /**
     * @var SubscriptionDefinition
     */
    protected $shippingSubscription;

    # --------------------------------------------------------------------------------

    /**
     * @return string
     */
    public function getSubscriptionId(): string
    {
        return $this->subscriptionId;
    }

    /**
     * @param string $subscriptionId
     */
    public function setSubscriptionId(string $subscriptionId): void
    {
        $this->subscriptionId = $subscriptionId;
    }

    /**
     * @return null|string
     */
    public function getSalutationId(): ?string
    {
        return $this->salutationId;
    }

    /**
     * @param null|string $salutationId
     */
    public function setSalutationId(?string $salutationId): void
    {
        $this->salutationId = $salutationId;
    }

    /**
     * @return null|string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param null|string $title
     */
    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * @param string $firstName
     */
    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    /**
     * @return string
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * @param string $lastName
     */
    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    /**
     * @return null|string
     */
    public function getCompany(): ?string
    {
        return $this->company;
    }

    /**
     * @param null|string $company
     */
    public function setCompany(?string $company): void
    {
        $this->company = $company;
    }

    /**
     * @return null|string
     */
    public function getDepartment(): ?string
    {
        return $this->department;
    }

    /**
     * @param null|string $department
     */
    public function setDepartment(?string $department): void
    {
        $this->department = $department;
    }

    /**
     * @return string
     */
    public function getStreet(): string
    {
        return $this->street;
    }

    /**
     * @param string $street
     */
    public function setStreet(string $street): void
    {
        $this->street = $street;
    }

    /**
     * @return string
     */
    public function getZipcode(): string
    {
        return $this->zipcode;
    }

    /**
     * @param string $zipcode
     */
    public function setZipcode(string $zipcode): void
    {
        $this->zipcode = $zipcode;
    }

    /**
     * @return string
     */
    public function getCity(): string
    {
        return $this->city;
    }

    /**
     * @param string $city
     */
    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    /**
     * @return string
     */
    public function getCountryId(): string
    {
        return $this->countryId;
    }

    /**
     * @param string $countryId
     */
    public function setCountryId(string $countryId): void
    {
        $this->countryId = $countryId;
    }

    /**
     * @return null|string
     */
    public function getCountryStateId(): ?string
    {
        return $this->countryStateId;
    }

    /**
     * @param null|string $countryStateId
     */
    public function setCountryStateId(?string $countryStateId): void
    {
        $this->countryStateId = $countryStateId;
    }

    /**
     * @return null|CountryEntity
     */
    public function getCountry(): ?CountryEntity
    {
        return $this->country;
    }

    /**
     * @param null|CountryEntity $country
     */
    public function setCountry(?CountryEntity $country): void
    {
        $this->country = $country;
    }

    /**
     * @return null|CountryStateEntity
     */
    public function getCountryState(): ?CountryStateEntity
    {
        return $this->countryState;
    }

    /**
     * @param null|CountryStateEntity $countryState
     */
    public function setCountryState(?CountryStateEntity $countryState): void
    {
        $this->countryState = $countryState;
    }

    /**
     * @return null|SubscriptionEntity
     */
    public function getSubscription(): ?SubscriptionEntity
    {
        return $this->subscription;
    }

    /**
     * @param null|SubscriptionEntity $subscription
     */
    public function setSubscription(?SubscriptionEntity $subscription): void
    {
        $this->subscription = $subscription;
    }

    /**
     * @return null|SalutationEntity
     */
    public function getSalutation(): ?SalutationEntity
    {
        return $this->salutation;
    }

    /**
     * @param null|SalutationEntity $salutation
     */
    public function setSalutation(?SalutationEntity $salutation): void
    {
        $this->salutation = $salutation;
    }

    /**
     * @return null|string
     */
    public function getVatId(): ?string
    {
        return $this->vatId;
    }

    /**
     * @param null|string $vatId
     */
    public function setVatId(?string $vatId): void
    {
        $this->vatId = $vatId;
    }

    /**
     * @return null|string
     */
    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    /**
     * @param null|string $phoneNumber
     */
    public function setPhoneNumber(?string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    /**
     * @return null|string
     */
    public function getAdditionalAddressLine1(): ?string
    {
        return $this->additionalAddressLine1;
    }

    /**
     * @param null|string $additionalAddressLine1
     */
    public function setAdditionalAddressLine1(?string $additionalAddressLine1): void
    {
        $this->additionalAddressLine1 = $additionalAddressLine1;
    }

    /**
     * @return null|string
     */
    public function getAdditionalAddressLine2(): ?string
    {
        return $this->additionalAddressLine2;
    }

    /**
     * @param null|string $additionalAddressLine2
     */
    public function setAdditionalAddressLine2(?string $additionalAddressLine2): void
    {
        $this->additionalAddressLine2 = $additionalAddressLine2;
    }

    /**
     * @return SubscriptionDefinition
     */
    public function getBillingSubscription(): SubscriptionDefinition
    {
        return $this->billingSubscription;
    }

    /**
     * @param SubscriptionDefinition $billingSubscription
     */
    public function setBillingSubscription(SubscriptionDefinition $billingSubscription): void
    {
        $this->billingSubscription = $billingSubscription;
    }

    /**
     * @return SubscriptionDefinition
     */
    public function getShippingSubscription(): SubscriptionDefinition
    {
        return $this->shippingSubscription;
    }

    /**
     * @param SubscriptionDefinition $shippingSubscription
     */
    public function setShippingSubscription(SubscriptionDefinition $shippingSubscription): void
    {
        $this->shippingSubscription = $shippingSubscription;
    }
}
