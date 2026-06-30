<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\StateHandler\OrderStateHandlerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;

final class FakeOrderStateHandler implements OrderStateHandlerInterface
{
    private bool $shouldThrow = false;
    private bool $shouldThrowIllegalTransition = false;
    private bool $called = false;

    public function setShouldThrow(bool $shouldThrow): void
    {
        $this->shouldThrow = $shouldThrow;
    }

    public function setShouldThrowIllegalTransition(bool $shouldThrow): void
    {
        $this->shouldThrowIllegalTransition = $shouldThrow;
    }

    public function wasCalled(): bool
    {
        return $this->called;
    }

    public function performTransition(OrderEntity $shopwareOrder, string $shopwarePaymentStatus, string $currentState, string $salesChannelId, Context $context): string
    {
        $this->called = true;
        if ($this->shouldThrowIllegalTransition) {
            throw new IllegalTransitionException($currentState, $shopwarePaymentStatus, ['reopen']);
        }
        if ($this->shouldThrow) {
            throw new \RuntimeException('FakeOrderStateHandler: forced failure');
        }

        return '';
    }
}
