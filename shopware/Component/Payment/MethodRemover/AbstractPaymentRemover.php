<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\MethodRemover;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('mollie.method.remover')]
abstract class AbstractPaymentRemover
{
    abstract public function remove(PaymentMethodCollection $paymentMethods,string $orderId, SalesChannelContext $salesChannelContext): PaymentMethodCollection;
}
