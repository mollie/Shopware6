<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\MethodRemover;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface PaymentMethodRemoverInterface
{
    public function remove(PaymentMethodCollection $paymentMethods, Request $request, SalesChannelContext $context): PaymentMethodCollection;
}
