<?php
declare(strict_types=1);

namespace Mollie\Unit\Mollie\Fake;

use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

final class FakeCustomerRepository
{
    public function getDefaultOrderCustomer():OrderCustomerEntity
    {
        $salutation = new SalutationEntity();
        $salutation->setDisplayName('Not specified');
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setCustomerNumber('100');
        $orderCustomer->setSalutation($salutation);
        $orderCustomer->setEmail('fake@unit.test');
        $orderCustomer->setFirstName('Tester');
        $orderCustomer->setLastName('Test');
        return $orderCustomer;
    }
}