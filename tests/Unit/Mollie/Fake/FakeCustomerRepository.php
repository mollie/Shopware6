<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Fake;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

final class FakeCustomerRepository
{
    public function getDefaultCustomer(): CustomerEntity
    {
        $salutation = new SalutationEntity();
        $salutation->setDisplayName('Not specified');
        $orderCustomer = $this->getDefaultCustomerWithoutSalutation();
        $orderCustomer->setSalutation($salutation);

        return $orderCustomer;
    }

    public function getDefaultCustomerWithoutSalutation(): CustomerEntity
    {
        $orderCustomer = new CustomerEntity();
        $orderCustomer->setCustomerNumber('100');
        $orderCustomer->setEmail('fake@unit.test');
        $orderCustomer->setFirstName('Tester');
        $orderCustomer->setLastName('Test');
        $orderCustomer->setGuest(false);

        return $orderCustomer;
    }
}
