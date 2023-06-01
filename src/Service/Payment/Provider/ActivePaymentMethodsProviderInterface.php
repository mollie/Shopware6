<?php

namespace Kiener\MolliePayments\Service\Payment\Provider;

use Mollie\Api\Resources\Method;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

interface ActivePaymentMethodsProviderInterface
{
    /**
     * @param float         $price
     * @param string        $currency
     * @param array<string> $salesChannelIDs
     * @return array<Method>
     */
    public function getActivePaymentMethodsForAmount(float $price, string $currency, array $salesChannelIDs): array;
}
