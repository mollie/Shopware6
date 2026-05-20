<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;

final class FakeOrderTransactionStateHandler extends OrderTransactionStateHandler
{
    private bool $shouldThrow = false;

    public function __construct()
    {
    }

    public function setShouldThrow(bool $shouldThrow): void
    {
        $this->shouldThrow = $shouldThrow;
    }

    public function reopen(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded();
    }

    public function fail(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded();
    }

    public function process(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded();
    }

    public function processUnconfirmed(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded();
    }

    public function paid(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded();
    }

    public function payPartially(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded();
    }

    public function refund(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded();
    }

    public function refundPartially(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded();
    }

    public function cancel(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded();
    }

    public function remind(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded();
    }

    public function authorize(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded();
    }

    public function chargeback(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded();
    }

    private function throwIfNeeded(): void
    {
        if ($this->shouldThrow) {
            throw new \RuntimeException('FakeOrderTransactionStateHandler: forced failure');
        }
    }
}
