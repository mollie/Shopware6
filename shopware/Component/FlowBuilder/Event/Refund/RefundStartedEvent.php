<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Event\Refund;

use Mollie\Shopware\Component\Refund\DAL\Refund\RefundDefinition;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
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

final class RefundStartedEvent extends Event implements OrderAware, MailAware, SalesChannelAware, FlowEventAware, ScalarValuesAware
{
    public const EVENT_NAME = 'mollie.refund.started';

    public function __construct(
        private readonly OrderEntity $order,
        private readonly RefundEntity $refund,
        private readonly float $amount,
        private readonly Context $context
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('amount', new ScalarValueType(ScalarValueType::TYPE_FLOAT))
            ->add('order', new EntityType(OrderDefinition::class))
            ->add('refund', new EntityType(RefundDefinition::class))
        ;
    }

    /**
     * @return array<string, null|array<mixed>|scalar>
     */
    public function getValues(): array
    {
        return [
            'amount' => $this->amount,
        ];
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getOrderId(): string
    {
        return $this->order->getId();
    }

    public function getRefund(): RefundEntity
    {
        return $this->refund;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getSalesChannelId(): string
    {
        return $this->order->getSalesChannelId();
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
}
