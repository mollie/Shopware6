<?php
declare(strict_types=1);

namespace Mollie\Shopware\Repository;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Shopware\Core\Framework\Context;

interface PaymentMethodRepositoryInterface
{
    public function getIdForPaymentMethod(PaymentMethod $paymentMethod,string $salesChannelId, Context $context): ?string;
}
