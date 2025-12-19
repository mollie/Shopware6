<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\PaymentMethodRepositoryInterface;
use Shopware\Core\Framework\Context;

final class FakePaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    public function getIdByPaymentHandler(string $handlerIdentifier, string $salesChannelId, Context $context): ?string
    {
        // TODO: Implement getIdByPaymentHandler() method.
    }

    public function getIdByPaymentMethod(PaymentMethod $paymentMethod, string $salesChannelId, Context $context): ?string
    {
        // TODO: Implement getIdByPaymentMethod() method.
    }
}
