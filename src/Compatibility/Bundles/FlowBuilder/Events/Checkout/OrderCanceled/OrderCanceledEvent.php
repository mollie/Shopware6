<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderCanceled;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Symfony\Contracts\EventDispatcher\Event;

class OrderCanceledEvent extends Event implements CustomerAware, OrderAware, MailAware, SalesChannelAware, BusinessEventInterface
{
    use JsonSerializableTrait;

    /**
     * @var OrderEntity
     */
    protected $order;

    /**
     * @var CustomerEntity
     */
    protected $customer;

    /**
     * @var Context
     */
    protected $context;

    public function __construct(OrderEntity $order, CustomerEntity $customer, Context $context)
    {
        $this->order = $order;
        $this->customer = $customer;
        $this->context = $context;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('order', new EntityType(OrderDefinition::class))
            ->add('customer', new EntityType(CustomerDefinition::class))
        ;
    }

    public function getName(): string
    {
        return 'mollie.checkout.order_canceled';
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getOrderId(): string
    {
        return $this->order->getId();
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
