<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\StateHandler\OrderStateHandlerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

final class FakeOrderStateHandler implements OrderStateHandlerInterface
{
    private bool $shouldThrow = false;

    public function setShouldThrow(bool $shouldThrow): void
    {
        $this->shouldThrow = $shouldThrow;
    }

    public function performTransition(OrderEntity $shopwareOrder, string $shopwarePaymentStatus, string $currentState, string $salesChannelId, Context $context): string
    {
        if ($this->shouldThrow) {
            throw new \RuntimeException('FakeOrderStateHandler: forced failure');
        }

        return '';
    }
}
