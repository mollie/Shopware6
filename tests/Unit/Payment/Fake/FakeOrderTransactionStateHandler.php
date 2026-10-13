<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;

final class FakeOrderTransactionStateHandler extends OrderTransactionStateHandler
{
    private bool $shouldThrow = false;
    private bool $shouldThrowIllegalTransition = false;
    private bool $called = false;
    private int $callCount = 0;
    private ?\Throwable $failure = null;
    private int $failureAttempts = 0;

    /** @var list<string> */
    private array $transitions = [];

    public function __construct()
    {
    }

    public function setShouldThrow(bool $shouldThrow): void
    {
        $this->shouldThrow = $shouldThrow;
    }

    public function setShouldThrowIllegalTransition(bool $shouldThrow): void
    {
        $this->shouldThrowIllegalTransition = $shouldThrow;
    }

    public function withFailure(\Throwable $failure, int $failureAttempts): void
    {
        $this->failure = $failure;
        $this->failureAttempts = $failureAttempts;
    }

    public function wasCalled(): bool
    {
        return $this->called;
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }

    /**
     * @return list<string>
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    public function reopen(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded(__FUNCTION__);
    }

    public function fail(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded(__FUNCTION__);
    }

    public function process(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded(__FUNCTION__);
    }

    public function processUnconfirmed(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded(__FUNCTION__);
    }

    public function paid(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded(__FUNCTION__);
    }

    public function payPartially(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded(__FUNCTION__);
    }

    public function refund(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded(__FUNCTION__);
    }

    public function refundPartially(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded(__FUNCTION__);
    }

    public function cancel(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded(__FUNCTION__);
    }

    public function remind(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded(__FUNCTION__);
    }

    public function authorize(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded(__FUNCTION__);
    }

    public function chargeback(string $transactionId, Context $context): void
    {
        $this->throwIfNeeded(__FUNCTION__);
    }

    private function throwIfNeeded(string $transition): void
    {
        $this->called = true;
        $this->transitions[] = $transition;
        ++$this->callCount;
        if ($this->failure !== null && $this->callCount <= $this->failureAttempts) {
            throw $this->failure;
        }
        if ($this->shouldThrowIllegalTransition) {
            throw new IllegalTransitionException('paid', 'paid', ['reopen']);
        }
        if ($this->shouldThrow) {
            throw new \RuntimeException('FakeOrderTransactionStateHandler: forced failure');
        }
    }
}
