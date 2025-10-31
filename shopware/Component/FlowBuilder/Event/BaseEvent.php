<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Event;

use Mollie\Shopware\Component\FlowBuilder\Event\EventData\PaymentType;
use Mollie\Shopware\Component\Mollie\Payment;
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
use Symfony\Contracts\EventDispatcher\Event;

abstract class BaseEvent extends Event implements BusinessEventInterface, MolliePaymentAware, OrderAware, CustomerAware, MailAware, SalesChannelAware
{
    public function __construct(private Payment $payment, private OrderEntity $order, private CustomerEntity $customer, private Context $context)
    {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class))
            ->add('order', new EntityType(OrderDefinition::class))
            ->add('payment', new PaymentType())
        ;
    }

    public function getPaymentId(): string
    {
        return $this->payment->getId();
    }

    public function getCustomerId(): string
    {
        return $this->customer->getId();
    }

    public function getOrderId(): string
    {
        return $this->order->getId();
    }

    public function getSalesChannelId(): string
    {
        return $this->order->getSalesChannelId();
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        $customerFullName = $this->customer->getFirstName() . ' ' . $this->customer->getLastName();

        return new MailRecipientStruct([
            $this->customer->getEmail() => $customerFullName,
        ]);
    }
}
