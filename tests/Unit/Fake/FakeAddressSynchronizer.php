<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Payment\ExpressMethod\AddressSynchronizerInterface;
use Mollie\Shopware\Component\Payment\ExpressMethod\AddressSyncResult;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeAddressSynchronizer implements AddressSynchronizerInterface
{
    private ?string $lastCustomerId = null;

    /** @var array<string, string> */
    private array $lastCountryMap = [];

    public function syncAddresses(
        CustomerEntity $customer,
        Address $shipping,
        ?Address $billing,
        SalesChannelContext $context,
        array $countryMap
    ): AddressSyncResult {
        $this->lastCustomerId = $customer->getId();
        $this->lastCountryMap = $countryMap;

        return new AddressSyncResult('shipping-id', 'billing-id');
    }

    public function getLastSyncedCustomerId(): ?string
    {
        return $this->lastCustomerId;
    }

    /** @return array<string, string> */
    public function getLastCountryMap(): array
    {
        return $this->lastCountryMap;
    }
}
