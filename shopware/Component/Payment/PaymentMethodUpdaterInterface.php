<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Shopware\Core\Framework\Context;

interface PaymentMethodUpdaterInterface
{
    public function updatePaymentMethod(PaymentMethodExtension $paymentMethodExtension,PaymentMethod $molliePaymentMethod, string $transactionId, string $orderNumber, string $salesChannelId, Context $context): string;
}
