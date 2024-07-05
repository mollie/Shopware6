<?php

namespace Kiener\MolliePayments\Service\Payment\Provider;

use Mollie\Api\Resources\Method;

interface ActivePaymentMethodsProviderInterface
{
    /**
     * @param float $price
     * @param string $currency
     * @param string $billingCountryCode
     * @param array<string> $salesChannelIDs
     * @return array<Method>
     */
    public function getActivePaymentMethodsForAmount(float $price, string $currency, string $billingCountryCode, array $salesChannelIDs): array;
}
