<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Shopware\Core\Framework\Context;

interface PaymentMethodRepositoryInterface
{
    public function getIdByPaymentHandler(string $handlerIdentifier, string $salesChannelId, Context $context): ?string;

    public function getIdByPaymentMethod(PaymentMethod $paymentMethod, string $salesChannelId, Context $context): ?string;
}
