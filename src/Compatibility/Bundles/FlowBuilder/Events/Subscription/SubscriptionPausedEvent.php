<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionDefinition;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Symfony\Contracts\EventDispatcher\Event;

class SubscriptionPausedEvent extends Event implements CustomerAware, MailAware, SalesChannelAware, BusinessEventInterface
{
    use JsonSerializableTrait;


    /**
     * @var SubscriptionEntity
     */
    private $subscription;

    /**
     * @var CustomerEntity
     */
    private $customer;

    /**
     * @var Context
     */
    private $context;


    /**
     * @param SubscriptionEntity $subscription
     * @param CustomerEntity $customer
     * @param Context $context
     */
    public function __construct(SubscriptionEntity $subscription, CustomerEntity $customer, Context $context)
    {
        $this->subscription = $subscription;
        $this->customer = $customer;
        $this->context = $context;
    }


    /**
     * @return EventDataCollection
     */
    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('subscription', new EntityType(SubscriptionDefinition::class))
            ->add('customer', new EntityType(CustomerDefinition::class));
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'mollie.subscription.paused';
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return SubscriptionEntity
     */
    public function getSubscription(): SubscriptionEntity
    {
        return $this->subscription;
    }

    /**
     * @return string
     */
    public function getCustomerId(): string
    {
        return $this->customer->getId();
    }

    /**
     * @return CustomerEntity
     */
    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    /**
     * @return MailRecipientStruct
     */
    public function getMailStruct(): MailRecipientStruct
    {
        return new MailRecipientStruct([
            $this->customer->getEmail() => sprintf('%s %s', $this->customer->getFirstName(), $this->customer->getLastName()),
        ]);
    }

    /**
     * @return string
     */
    public function getSalesChannelId(): string
    {
        return $this->customer->getSalesChannelId();
    }
}
