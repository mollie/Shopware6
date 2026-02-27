<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Action;

use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionActionEvent;
use Mollie\Shopware\Component\Subscription\SubscriptionDataStruct;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('mollie.subscription.action')]
abstract class AbstractAction
{
    abstract public function execute(SubscriptionDataStruct $subscriptionData, SubscriptionSettings $settings, Subscription $mollieSubscription, string $orderNumber, Context $context): Subscription;

    /**
     * @return class-string<SubscriptionActionEvent>
     */
    abstract public function getEventClass(): string;

    abstract public static function getActioName(): string;

    public function supports(string $actionName): bool
    {
        return $actionName === $this::getActioName();
    }

    public function getEvent(SubscriptionEntity $subscription, CustomerEntity $customer, Context $context): SubscriptionActionEvent
    {
        $eventClass = $this->getEventClass();

        return new $eventClass($subscription, $customer, $context);
    }
}
