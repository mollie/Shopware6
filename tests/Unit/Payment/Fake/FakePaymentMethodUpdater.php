<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\PaymentMethodUpdaterInterface;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Shopware\Core\Framework\Context;

final class FakePaymentMethodUpdater implements PaymentMethodUpdaterInterface
{
    private bool $shouldThrow = false;
    private bool $called = false;

    public function setShouldThrow(bool $shouldThrow): void
    {
        $this->shouldThrow = $shouldThrow;
    }

    public function wasCalled(): bool
    {
        return $this->called;
    }

    public function updatePaymentMethod(PaymentMethodExtension $paymentMethodExtension, PaymentMethod $molliePaymentMethod, string $transactionId, string $orderNumber, string $salesChannelId, Context $context): string
    {
        $this->called = true;
        if ($this->shouldThrow) {
            throw new \RuntimeException('FakePaymentMethodUpdater: forced failure');
        }

        return '';
    }
}
