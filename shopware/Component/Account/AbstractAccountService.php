<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Account;

use Mollie\Shopware\Component\Mollie\Address;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractAccountService
{
    abstract public function getDecorated(): self;

    abstract public function loginOrCreateAccount(string $paymentMethodId, Address $billingAddress, Address $shippingAddress, SalesChannelContext $salesChannelContext): SalesChannelContext;
}
