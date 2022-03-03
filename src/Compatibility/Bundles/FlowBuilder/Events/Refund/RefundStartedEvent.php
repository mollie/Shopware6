<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund;


use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\OrderAware;
use Symfony\Contracts\EventDispatcher\Event;

class RefundStartedEvent extends Event implements OrderAware, BusinessEventInterface
{

    /**
     * @var OrderEntity
     */
    private $order;

    /**
     * @var Context
     */
    private $context;


    /**
     * @param OrderEntity $orderEntity
     * @param Context $context
     */
    public function __construct(OrderEntity $orderEntity, Context $context)
    {
        $this->order = $orderEntity;
        $this->context = $context;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'mollie.refund.started';
    }

    /**
     * @return EventDataCollection
     */
    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('order', new EntityType(OrderDefinition::class));
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
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

}