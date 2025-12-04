<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\MethodRemover;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag('mollie.method.remover')]
final class VoucherPaymentMethodRemover implements PaymentMethodRemoverInterface
{
    public function remove(PaymentMethodCollection $paymentMethods, Request $request, SalesChannelContext $context): PaymentMethodCollection
    {
        return $paymentMethods;
    }
}
