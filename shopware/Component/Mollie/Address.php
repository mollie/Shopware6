<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class Address implements \JsonSerializable
{
    use JsonSerializableTrait;
    private string $title;
    private string $givenName;
    private string $familyName;
    private string $organizationName;
    private string $streetAndNumber;
    private string $streetAdditional;
    private string $postalCode;
    private string $email;
    private string $phone;
    private string $city;
    private string $country;

    public function __construct(string $email, string $title, string $givenName, string $familyName, string $streetAndNumber, string $postalCode, string $city, string $country)
    {
        $this->email = $email;
        $this->title = $title;
        $this->givenName = $givenName;
        $this->familyName = $familyName;
        $this->streetAndNumber = $streetAndNumber;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->country = $country;
    }

    public static function fromAddress(?OrderCustomerEntity $customer, ?OrderAddressEntity $billingAddress): self
    {
        if ($customer === null) {
            throw new \InvalidArgumentException('Customer cannot be null');
        }
        if ($billingAddress === null) {
            throw new \InvalidArgumentException('Billing address should not be null.');
        }
        $address = new self($customer->getEmail(),
            $customer->getSalutation()->getDisplayName(),
            $billingAddress->getFirstName(),
            $billingAddress->getLastName(),
            $billingAddress->getStreet(),
            $billingAddress->getZipcode(),
            $billingAddress->getCity(),
            $billingAddress->getCountry()->getIso()
        );

        if ($billingAddress->getPhoneNumber() !== null) {
            $address->setPhone($billingAddress->getPhoneNumber());
        }
        $additionalAddressLine = '';
        if ($billingAddress->getAdditionalAddressLine1()) {
            $additionalAddressLine .= $billingAddress->getAdditionalAddressLine1();
        }
        if ($billingAddress->getAdditionalAddressLine2()) {
            $additionalAddressLine .= $billingAddress->getAdditionalAddressLine2();
        }
        if (mb_strlen($additionalAddressLine) > 0) {
            $address->setStreetAdditional($additionalAddressLine);
        }
        if ($billingAddress->getCompany() !== null) {
            $billingAddress->setCompany($billingAddress->getCompany());
        }

        return $address;
    }

    public function setOrganizationName(string $organizationName): void
    {
        $this->organizationName = $organizationName;
    }

    public function setStreetAdditional(string $streetAdditional): void
    {
        $this->streetAdditional = $streetAdditional;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getGivenName(): string
    {
        return $this->givenName;
    }

    public function getFamilyName(): string
    {
        return $this->familyName;
    }

    public function getOrganizationName(): string
    {
        return $this->organizationName;
    }

    public function getStreetAndNumber(): string
    {
        return $this->streetAndNumber;
    }

    public function getStreetAdditional(): string
    {
        return $this->streetAdditional;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCountry(): string
    {
        return $this->country;
    }
}
