<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\Mollie\Exception\MissingCountryException;
use Mollie\Shopware\Component\Mollie\Exception\MissingSalutationException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;

final class Address implements \JsonSerializable
{
    public const CUSTOM_FIELDS_KEY = 'mollie_payments_express_address_id';
    private string $title;
    private string $givenName;
    private string $familyName;
    private string $organizationName = '';
    private string $streetAndNumber;
    private string $streetAdditional = '';
    private string $postalCode;
    private string $email;
    private string $phone = '';
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

    public static function fromCustomerAddress(CustomerAddressEntity $customerAddress): self
    {
        $customer = $customerAddress->getCustomer();
        $country = $customerAddress->getCountry();

        $address = new self(
            $customer !== null ? $customer->getEmail() : '',
            '',
            (string) $customerAddress->getFirstName(),
            (string) $customerAddress->getLastName(),
            (string) $customerAddress->getStreet(),
            (string) $customerAddress->getZipcode(),
            (string) $customerAddress->getCity(),
            $country !== null ? (string) $country->getIso() : '',
        );

        $additionalAddressLines = [];
        if ($customerAddress->getAdditionalAddressLine1()) {
            $additionalAddressLines[] = $customerAddress->getAdditionalAddressLine1();
        }
        if ($customerAddress->getAdditionalAddressLine2()) {
            $additionalAddressLines[] = $customerAddress->getAdditionalAddressLine2();
        }
        if (count($additionalAddressLines) > 0) {
            $address->setStreetAdditional(implode(' ', $additionalAddressLines));
        }

        $phone = $customerAddress->getPhoneNumber();
        if ($phone !== null && $phone !== '') {
            $address->setPhone($phone);
        }

        $company = $customerAddress->getCompany();
        if ($company !== null && $company !== '') {
            $address->setOrganizationName($company);
        }

        return $address;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function fromResponseBody(array $body): self
    {
        $address = new self($body['email'] ?? '',
            '',
            $body['givenName'] ?? '',
            $body['familyName'] ?? '',
            $body['streetAndNumber'] ?? '',
            $body['postalCode'] ?? '',
            $body['city'] ?? '',
            $body['country'] ?? ''
        );
        $phone = $body['phone'] ?? null;
        $streetAdditional = $body['streetAdditional'] ?? null;
        if ($streetAdditional !== null) {
            $address->setStreetAdditional($streetAdditional);
        }
        if ($phone !== null) {
            $address->setPhone($phone);
        }

        return $address;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'title' => trim($this->title),
            'givenName' => trim($this->givenName),
            'familyName' => trim($this->familyName),
            'streetAndNumber' => trim($this->streetAndNumber),
            'postalCode' => trim($this->postalCode),
            'email' => trim($this->email),
            'city' => trim($this->city),
            'country' => trim($this->country),
        ];

        $streetAdditional = trim($this->streetAdditional);
        if ($streetAdditional !== '') {
            $data['streetAdditional'] = $streetAdditional;
        }
        $organizationName = trim($this->organizationName);
        if ($organizationName !== '') {
            $data['organizationName'] = $organizationName;
        }
        $phone = trim($this->phone);
        if ($phone !== '') {
            $data['phone'] = $phone;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function toRegisterFormArray(): array
    {
        return [
            'firstName' => $this->givenName,
            'lastName' => $this->familyName,
            'email' => $this->email,
            'street' => $this->streetAndNumber,
            'zipcode' => $this->postalCode,
            'city' => $this->city,
        ];
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

    public function getId(): string
    {
        $keys = [
            $this->givenName,
            $this->familyName,
            $this->email,
            $this->streetAndNumber,
            $this->streetAdditional,
            $this->postalCode,
            $this->city,
            $this->country
        ];
        if (mb_strlen($this->organizationName) > 0) {
            $keys[] = $this->organizationName;
        }
        if (mb_strlen($this->phone) > 0) {
            $keys[] = $this->phone;
        }

        return md5(implode('-', $keys));
    }
}
