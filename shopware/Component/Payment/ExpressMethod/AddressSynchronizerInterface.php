<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

use Mollie\Shopware\Component\Mollie\Address;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface AddressSynchronizerInterface
{
    /**
     * @param array<string, string> $countryMap pre-built ISO → country UUID mapping
     */
    public function syncAddresses(
        CustomerEntity $customer,
        Address $shipping,
        ?Address $billing,
        SalesChannelContext $context,
        array $countryMap
    ): AddressSyncResult;
}
