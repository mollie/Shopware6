<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Builder;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

final class CustomerBuilder
{
    private string $id = 'customer-id';
    private string $email = 'test@example.com';
    private string $firstName = 'Test';
    private string $lastName = 'Customer';
    private string $customerNumber = '100';
    private bool $guest = false;
    private ?SalutationEntity $salutation = null;
    private ?CustomerAddressEntity $defaultBillingAddress = null;

    public static function create(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->email = $email;

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

    public function withCustomerNumber(string $customerNumber): self
    {
        $this->customerNumber = $customerNumber;

        return $this;
    }

    public function asGuest(): self
    {
        $this->guest = true;

        return $this;
    }

    public function withSalutation(SalutationEntity $salutation): self
    {
        $this->salutation = $salutation;

        return $this;
    }

    public function withDefaultBillingAddress(CustomerAddressEntity $address): self
    {
        $this->defaultBillingAddress = $address;

        return $this;
    }

    public function build(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId($this->id);
        $customer->setEmail($this->email);
        $customer->setFirstName($this->firstName);
        $customer->setLastName($this->lastName);
        $customer->setCustomerNumber($this->customerNumber);
        $customer->setGuest($this->guest);

        if ($this->salutation instanceof SalutationEntity) {
            $customer->setSalutation($this->salutation);
        }

        if ($this->defaultBillingAddress instanceof CustomerAddressEntity) {
            $customer->setDefaultBillingAddress($this->defaultBillingAddress);
        }

        return $customer;
    }
}
