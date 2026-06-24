<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Framework\Context;

class FakeRecalculationService extends RecalculationService
{
    public ?LineItem $capturedLineItem = null;
    public bool $recalculateCalled = false;

    public function __construct()
    {
    }

    public function addCustomLineItem(string $orderId, LineItem $lineItem, Context $context): void
    {
        $this->capturedLineItem = $lineItem;
    }

    public function recalculateOrder(string $orderId, Context $context, array $salesChannelContextOptions = []): void
    {
        $this->recalculateCalled = true;
    }
}
