<?php

namespace Kiener\MolliePayments\Service\Payment\Provider;

use Mollie\Api\Resources\Method;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

interface ActivePaymentMethodsProviderInterface
{
    /**
     * @param array<array|scalar> $parameters
     * @param array<SalesChannelEntity> $salesChannels
     * @return array<Method>
     */
    public function getActivePaymentMethods(array $parameters = [], array $salesChannels = []): array;

    /**
     * @param Cart $cart
     * @param string $currency
     * @param array<SalesChannelEntity> $salesChannels
     * @return array<Method>
     */
    public function getActivePaymentMethodsForAmount(Cart $cart, string $currency, array $salesChannels = []): array;
}