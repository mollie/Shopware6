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
        $orderCustomer = new CustomerEntity();
        $orderCustomer->setCustomerNumber('100');
        $orderCustomer->setSalutation($salutation);
        $orderCustomer->setEmail('fake@unit.test');
        $orderCustomer->setFirstName('Tester');
        $orderCustomer->setLastName('Test');

        return $orderCustomer;
    }
}
