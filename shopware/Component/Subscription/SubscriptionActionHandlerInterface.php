<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionActionEvent;
use Shopware\Core\Framework\Context;

interface SubscriptionActionHandlerInterface
{
    public function handle(string $action, string $subscriptionId, Context $context): Subscription;

    /**
     * @return array<class-string<SubscriptionActionEvent>>
     */
    public function getActionEvents(): array;
}
