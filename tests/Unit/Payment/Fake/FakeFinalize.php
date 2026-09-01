<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Payment\Action\FinalizeInterface;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Component\Payment\Transaction\MollieTransactionStruct;
use Shopware\Core\Framework\Context;

final class FakeFinalize implements FinalizeInterface
{
    private int $callCount = 0;

    private ?MollieTransactionStruct $lastTransaction = null;

    public function __construct(private readonly ?\Throwable $failure = null)
    {
    }

    public function execute(
        AbstractMolliePaymentHandler $paymentHandler,
        MollieTransactionStruct $transaction,
        Context $context
    ): void {
        ++$this->callCount;
        $this->lastTransaction = $transaction;

        if ($this->failure !== null) {
            throw $this->failure;
        }
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }

    public function getLastTransaction(): ?MollieTransactionStruct
    {
        return $this->lastTransaction;
    }
}
