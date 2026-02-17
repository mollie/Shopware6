<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived;

use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractWebhookReceivedEvent extends Event implements OrderAware, MailAware, SalesChannelAware, FlowEventAware
{
    /**
     * @var OrderEntity
     */
    private $order;

    /**
     * @var Context
     */
    private $context;

    public function __construct(OrderEntity $orderEntity, Context $context)
    {
        $this->order = $orderEntity;
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'mollie.webhook_received.status.' . $this->getMollieStatus();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('order', new EntityType(OrderDefinition::class))
            ->add('mollieStatus', new ScalarValueType('string'))
        ;
    }

    public function getOrderId(): string
    {
        return $this->order->getId();
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    abstract public function getMollieStatus(): string;

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        $customer = $this->order->getOrderCustomer();

        if (! $customer instanceof OrderCustomerEntity) {
            return new MailRecipientStruct([]);
        }

        return new MailRecipientStruct([
            $customer->getEmail() => sprintf('%s %s', $customer->getFirstName(), $customer->getLastName()),
        ]);
    }

    public function getSalesChannelId(): string
    {
        return $this->order->getSalesChannelId();
    }
}
