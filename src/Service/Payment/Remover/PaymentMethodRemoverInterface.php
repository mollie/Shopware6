<?php

namespace Kiener\MolliePayments\Service\Payment\Remover;

use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface PaymentMethodRemoverInterface
{
    /**
     * @param PaymentMethodRouteResponse $originalData
     * @param SalesChannelContext        $context
     * @return PaymentMethodRouteResponse
     */
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse;
}
