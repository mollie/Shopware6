<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Event\Webhook;

use Mollie\Shopware\Component\FlowBuilder\Event\EventData\PaymentType;
use Mollie\Shopware\Component\FlowBuilder\Event\MolliePaymentAware;
use Mollie\Shopware\Component\Mollie\Payment;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Symfony\Contracts\EventDispatcher\Event;

class WebhookEvent extends Event implements MolliePaymentAware, OrderAware, MailAware, SalesChannelAware, FlowEventAware
{
    public function __construct(private Payment $payment, private OrderEntity $order, private Context $context)
    {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('order', new EntityType(OrderDefinition::class))
            ->add('payment', new PaymentType())
        ;
    }

    public function getName(): string
    {
        return 'mollie.webhook_received.' . self::getStatus();
    }

    public function getMailStruct(): MailRecipientStruct
    {
        $customer = $this->order->getOrderCustomer();
        if ($customer === null) {
            return new MailRecipientStruct([]);
        }

        $customerFullName = $customer->getFirstName() . ' ' . $customer->getLastName();

        return new MailRecipientStruct([
            $customer->getEmail() => $customerFullName,
        ]);
    }

    public function getSalesChannelId(): string
    {
        return $this->order->getSalesChannelId();
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getPaymentId(): string
    {
        return $this->payment->getId();
    }

    public function getOrderId(): string
    {
        return $this->order->getId();
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    protected static function getStatus(): string
    {
        return 'all';
    }
}
