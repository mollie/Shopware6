<?php
declare(strict_types=1);

namespace Mollie\Shopware\Repository;

use Shopware\Core\Framework\Context;

interface PaymentMethodRepositoryInterface
{
    public function getIdForPaymentMethod(string $handlerIdentifier,string $salesChannelId, Context $context): ?string;
}
