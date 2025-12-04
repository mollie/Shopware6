<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\Mollie\Exception\MissingCountryException;
use Mollie\Shopware\Component\Mollie\Exception\MissingSalutationException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
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

    public static function fromAddress(CustomerEntity $customer, OrderAddressEntity $orderAddress): self
    {
        $salutation = $customer->getSalutation();
        if ($salutation === null) {
            throw new MissingSalutationException();
        }
        $country = $orderAddress->getCountry();
        if ($country === null) {
            throw new MissingCountryException();
        }
        $address = new self($customer->getEmail(),
            (string) $salutation->getDisplayName(),
            $orderAddress->getFirstName(),
            $orderAddress->getLastName(),
            $orderAddress->getStreet(),
            (string) $orderAddress->getZipcode(),
            $orderAddress->getCity(),
            (string) $country->getIso()
        );

        if ($orderAddress->getPhoneNumber() !== null) {
            $address->setPhone($orderAddress->getPhoneNumber());
        }
        $additionalAddressLines = [];
        if ($orderAddress->getAdditionalAddressLine1()) {
            $additionalAddressLines[] = $orderAddress->getAdditionalAddressLine1();
        }
        if ($orderAddress->getAdditionalAddressLine2()) {
            $additionalAddressLines[] = $orderAddress->getAdditionalAddressLine2();
        }
        if (count($additionalAddressLines) > 0) {
            $address->setStreetAdditional(implode(' ', $additionalAddressLines));
        }
        if ($orderAddress->getCompany() !== null) {
            $address->setOrganizationName($orderAddress->getCompany());
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
