<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\Address;

final class AddressStruct
{
    /**
     * @var string
     */
    private $firstName;

    /**
     * @var string
     */
    private $lastName;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $street;

    /**
     * @var string
     */
    private $streetAdditional;

    /**
     * @var string
     */
    private $zipCode;

    /**
     * @var string
     */
    private $city;

    /**
     * @var string
     */
    private $countryCode;

    /**
     * @var string
     */
    private $phone;

    /**
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $street
     * @param string $streetAdditional
     * @param string $zipCode
     * @param string $city
     * @param string $countryCode
     */
    public function __construct(string $firstName, string $lastName, string $email, string $street, string $streetAdditional, string $zipCode, string $city, string $countryCode, string $phone)
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->street = $street;
        $this->streetAdditional = $streetAdditional;
        $this->zipCode = $zipCode;
        $this->city = $city;
        $this->countryCode = $countryCode;
        $this->email = $email;
        $this->phone = $phone;
    }

    /**
     * @param \stdClass $address
     * @return self
     */
    public static function createFromApiResponse(\stdClass $address)
    {
        $streetAdditional = '';
        if (property_exists($address, 'streetAdditional')) {
            $streetAdditional = $address->streetAdditional;
        }

        return new AddressStruct($address->givenName, $address->familyName, $address->email, $address->streetAndNumber, $streetAdditional, $address->postalCode, $address->city, $address->country, $address->phone);
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getStreetAdditional(): string
    {
        return $this->streetAdditional;
    }

    public function getZipCode(): string
    {
        return $this->zipCode;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }


    public function getMollieAddressId(): string
    {
        return md5(implode('-', [$this->firstName, $this->lastName, $this->email, $this->street, $this->streetAdditional, $this->zipCode, $this->city, $this->countryCode]));
    }
}
