<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Event;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionDefinition;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Symfony\Contracts\EventDispatcher\Event;

abstract class SubscriptionActionEvent extends Event implements SubscriptionAware, MailAware, SalesChannelAware, FlowEventAware
{
    public function __construct(private readonly SubscriptionEntity $subscription,
        private readonly CustomerEntity $customer,
        private readonly Context $context)
    {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('subscription', new EntityType(SubscriptionDefinition::class))
            ->add('customer', new EntityType(CustomerDefinition::class))
        ;
    }

    public function getName(): string
    {
        return 'mollie.subscription.' . $this->getEventName();
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return new MailRecipientStruct([
            $this->customer->getEmail() => sprintf('%s %s', $this->customer->getFirstName(), $this->customer->getLastName()),
        ]);
    }

    public function getSalesChannelId(): string
    {
        return $this->subscription->getSalesChannelId();
    }

    public function getSubscription(): SubscriptionEntity
    {
        return $this->subscription;
    }

    public function getSubscriptionId(): string
    {
        return $this->subscription->getId();
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    abstract protected function getEventName(): string;
}
