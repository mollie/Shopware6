<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

if (interface_exists(__NAMESPACE__ . '\\CustomerAware')) {
    return;
}

interface CustomerAware extends BusinessEventInterface
{
    public const CUSTOMER_ID = 'customerId';

    public const CUSTOMER = 'customer';

    public function getCustomerId(): string;
}
