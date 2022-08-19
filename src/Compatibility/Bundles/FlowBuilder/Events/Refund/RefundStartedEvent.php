<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund;

use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Symfony\Contracts\EventDispatcher\Event;

class RefundStartedEvent extends Event implements OrderAware, MailAware, SalesChannelAware, BusinessEventInterface
{

    /**
     * @var OrderEntity
     */
    private $order;

    /**
     * @var float
     */
    private $amount;

    /**
     * @var Context
     */
    private $context;


    /**
     * @param OrderEntity $orderEntity
     * @param float $amount
     * @param Context $context
     */
    public function __construct(OrderEntity $orderEntity, float $amount, Context $context)
    {
        $this->order = $orderEntity;
        $this->amount = $amount;
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
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        $customer = $this->order->getOrderCustomer();

        if (!$customer instanceof OrderCustomerEntity) {
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
