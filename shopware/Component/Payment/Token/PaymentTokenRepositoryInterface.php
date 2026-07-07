<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Token;

interface PaymentTokenRepositoryInterface
{
    public function isConsumed(string $paymentToken): bool;
}
