<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

final class AddressSyncResult
{
    public function __construct(
        private readonly string $shippingAddressId,
        private readonly string $billingAddressId,
    ) {
    }

    public function getShippingAddressId(): string
    {
        return $this->shippingAddressId;
    }

    public function getBillingAddressId(): string
    {
        return $this->billingAddressId;
    }
}
