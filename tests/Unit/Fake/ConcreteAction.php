<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Action\AbstractAction;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionCancelledEvent;
use Mollie\Shopware\Component\Subscription\SubscriptionDataStruct;
use Shopware\Core\Framework\Context;

final class ConcreteAction extends AbstractAction
{
    public function execute(SubscriptionDataStruct $subscriptionData, SubscriptionSettings $settings, Subscription $mollieSubscription, string $orderNumber, Context $context): Subscription
    {
        throw new \LogicException('execute() is not exercised in tests using ConcreteAction');
    }

    public function getEventClass(): string
    {
        return SubscriptionCancelledEvent::class;
    }

    public static function getActioName(): string
    {
        return 'concrete-test-action';
    }
}
