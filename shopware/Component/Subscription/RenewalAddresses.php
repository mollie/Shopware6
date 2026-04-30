<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

final class RenewalAddresses
{
    public function __construct(
        private readonly string $billingAddressId,
        private readonly string $shippingAddressId
    ) {
    }

    public function getBillingAddressId(): string
    {
        return $this->billingAddressId;
    }

    public function getShippingAddressId(): string
    {
        return $this->shippingAddressId;
    }
}
