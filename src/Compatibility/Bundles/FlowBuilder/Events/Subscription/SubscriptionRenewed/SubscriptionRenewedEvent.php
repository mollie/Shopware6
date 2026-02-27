<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionRenewed;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionDefinition;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Symfony\Contracts\EventDispatcher\Event;

class SubscriptionRenewedEvent extends Event implements CustomerAware, MailAware, SalesChannelAware, FlowEventAware
{
    use JsonSerializableTrait;

    /**
     * @var SubscriptionEntity
     */
    protected $subscription;

    /**
     * @var CustomerEntity
     */
    protected $customer;

    /**
     * @var Context
     */
    protected $context;

    public function __construct(SubscriptionEntity $subscription, CustomerEntity $customer, Context $context)
    {
        $this->subscription = $subscription;
        $this->customer = $customer;
        $this->context = $context;
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
        return 'mollie.subscription.renewed';
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getSubscription(): SubscriptionEntity
    {
        return $this->subscription;
    }

    public function getCustomerId(): string
    {
        return $this->customer->getId();
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return new MailRecipientStruct([
            $this->customer->getEmail() => sprintf('%s %s', $this->customer->getFirstName(), $this->customer->getLastName()),
        ]);
    }

    public function getSalesChannelId(): string
    {
        return $this->customer->getSalesChannelId();
    }
}
