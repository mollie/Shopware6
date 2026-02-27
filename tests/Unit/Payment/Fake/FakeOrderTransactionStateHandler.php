<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;

final class FakeOrderTransactionStateHandler extends OrderTransactionStateHandler
{
    public function __construct()
    {
    }

    public function reopen(string $transactionId, Context $context): void
    {
    }

    public function fail(string $transactionId, Context $context): void
    {
    }

    public function process(string $transactionId, Context $context): void
    {
    }

    public function processUnconfirmed(string $transactionId, Context $context): void
    {
    }

    public function paid(string $transactionId, Context $context): void
    {
    }

    public function payPartially(string $transactionId, Context $context): void
    {
    }

    public function refund(string $transactionId, Context $context): void
    {
    }

    public function refundPartially(string $transactionId, Context $context): void
    {
    }

    public function cancel(string $transactionId, Context $context): void
    {
    }

    public function remind(string $transactionId, Context $context): void
    {
    }

    public function authorize(string $transactionId, Context $context): void
    {
    }

    public function chargeback(string $transactionId, Context $context): void
    {
    }
}
