<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund\RefundStarted;

use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Symfony\Contracts\EventDispatcher\Event;

class RefundStartedEvent extends Event implements OrderAware, MailAware, SalesChannelAware, BusinessEventInterface
{
    /**
     * @var OrderEntity
     */
    protected $order;

    /**
     * @var float
     */
    protected $amount;

    /**
     * @var Context
     */
    protected $context;

    public function __construct(OrderEntity $orderEntity, float $amount, Context $context)
    {
        $this->order = $orderEntity;
        $this->amount = $amount;
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'mollie.refund.started';
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('amount', new ScalarValueType(ScalarValueType::TYPE_FLOAT))
            ->add('order', new EntityType(OrderDefinition::class))
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

    public function getAmount(): float
    {
        return $this->amount;
    }

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
