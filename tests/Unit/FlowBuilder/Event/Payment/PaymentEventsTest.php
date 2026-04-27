<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Event\Payment;

use Mollie\Shopware\Component\FlowBuilder\Event\Payment\CancelledEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Payment\FailedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Payment\SuccessEvent;
use Mollie\Shopware\Component\Mollie\Payment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

#[CoversClass(SuccessEvent::class)]
#[CoversClass(CancelledEvent::class)]
#[CoversClass(FailedEvent::class)]
final class PaymentEventsTest extends TestCase
{
    public function testSuccessEventName(): void
    {
        $event = $this->buildSuccessEvent();

        $this->assertSame(SuccessEvent::EVENT_NAME, $event->getName());
        $this->assertSame('mollie.payment.success', $event->getName());
    }

    public function testCancelledEventName(): void
    {
        $payment = new Payment('tr_test');
        $order = $this->buildOrder();
        $customer = $this->buildCustomer();
        $context = new Context(new SystemSource());

        $event = new CancelledEvent($payment, $order, $customer, $context);

        $this->assertSame(CancelledEvent::EVENT_NAME, $event->getName());
        $this->assertSame('mollie.payment.cancelled', $event->getName());
    }

    public function testFailedEventName(): void
    {
        $payment = new Payment('tr_test');
        $order = $this->buildOrder();
        $customer = $this->buildCustomer();
        $context = new Context(new SystemSource());

        $event = new FailedEvent($payment, $order, $customer, $context);

        $this->assertSame(FailedEvent::EVENT_NAME, $event->getName());
        $this->assertSame('mollie.payment.failed', $event->getName());
    }

    public function testGetters(): void
    {
        $payment = new Payment('tr_abc');
        $order = $this->buildOrder('order-1', 'sc-1');
        $customer = $this->buildCustomer('cust-1');
        $context = new Context(new SystemSource());

        $event = new SuccessEvent($payment, $order, $customer, $context);

        $this->assertSame($payment, $event->getPayment());
        $this->assertSame('tr_abc', $event->getPaymentId());
        $this->assertSame('order-1', $event->getOrderId());
        $this->assertSame('sc-1', $event->getSalesChannelId());
        $this->assertSame('cust-1', $event->getCustomerId());
        $this->assertSame($order, $event->getOrder());
        $this->assertSame($customer, $event->getCustomer());
        $this->assertSame($context, $event->getContext());
    }

    public function testGetMailStructReturnsCustomerEmail(): void
    {
        $event = $this->buildSuccessEvent();

        $mailStruct = $event->getMailStruct();
        $recipients = $mailStruct->getRecipients();

        $this->assertArrayHasKey('john@example.com', $recipients);
        $this->assertSame('John Doe', $recipients['john@example.com']);
    }

    public function testGetAvailableData(): void
    {
        $collection = SuccessEvent::getAvailableData();

        $this->assertNotNull($collection);
    }

    private function buildSuccessEvent(): SuccessEvent
    {
        $payment = new Payment('tr_test');
        $order = $this->buildOrder();
        $customer = $this->buildCustomer();
        $context = new Context(new SystemSource());

        return new SuccessEvent($payment, $order, $customer, $context);
    }

    private function buildOrder(string $id = 'order-abc', string $salesChannelId = 'sc-xyz'): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId($id);
        $order->setSalesChannelId($salesChannelId);

        return $order;
    }

    private function buildCustomer(string $id = 'cust-abc'): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId($id);
        $customer->setFirstName('John');
        $customer->setLastName('Doe');
        $customer->setEmail('john@example.com');

        return $customer;
    }
}
