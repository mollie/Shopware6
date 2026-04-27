<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

/**
 * Carries the resolved Shopware customer-address IDs after an express-checkout
 * address sync. Callers can use these IDs to feed the correct addresses into
 * subsequent checkout requests.
 */
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
