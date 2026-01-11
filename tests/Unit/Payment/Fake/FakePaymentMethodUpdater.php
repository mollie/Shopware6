<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\PaymentMethodUpdaterInterface;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Shopware\Core\Framework\Context;

final class FakePaymentMethodUpdater implements PaymentMethodUpdaterInterface
{
    public function updatePaymentMethod(PaymentMethodExtension $paymentMethodExtension, PaymentMethod $molliePaymentMethod, string $transactionId, string $orderNumber, string $salesChannelId, Context $context): string
    {
        return '';
    }
}
