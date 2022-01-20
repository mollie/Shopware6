<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events;


use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\OrderAware;
use Symfony\Contracts\EventDispatcher\Event;


class WebhookReceivedEvent extends Event implements OrderAware, BusinessEventInterface
{

    /**
     * @var OrderEntity
     */
    private $order;

    /**
     * @var string
     */
    private $status;

    /**
     * @var Context
     */
    private $context;


    /**
     * @param OrderEntity $orderEntity
     * @param string $status
     * @param Context $context
     */
    public function __construct(OrderEntity $orderEntity, string $status, Context $context)
    {
        $this->order = $orderEntity;
        $this->status = $status;
        $this->context = $context;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'mollie.webhook_received.All';
    }

    /**
     * @return EventDataCollection
     */
    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('order', new EntityType(OrderDefinition::class))
            ->add('mollieStatus', new ScalarValueType('string'));
    }

    /**
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->order->getId();
    }

    /**
     * @return OrderEntity
     */
    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    /**
     * @return string
     */
    public function getMollieStatus(): string
    {
        return $this->status;
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

}
