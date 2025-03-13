<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Payment\Remover;

use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface PaymentMethodRemoverInterface
{
    public function removePaymentMethods(PaymentMethodRouteResponse $originalData, SalesChannelContext $context): PaymentMethodRouteResponse;
}
