<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Event\Payment;

use Mollie\Shopware\Component\FlowBuilder\Event\Payment\AbstractPaymentEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Payment\CancelledEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Payment\FailedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Payment\SuccessEvent;
use Mollie\Shopware\Component\Mollie\Payment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

/**
 * These events are what a merchant builds a flow on, so the data a flow can read from them - order,
 * customer, payment and the mail recipient - has to be there.
 */
#[CoversClass(AbstractPaymentEvent::class)]
#[CoversClass(SuccessEvent::class)]
#[CoversClass(FailedEvent::class)]
#[CoversClass(CancelledEvent::class)]
final class PaymentEventsTest extends TestCase
{
    /**
     * @param class-string<AbstractPaymentEvent> $eventClass
     */
    #[DataProvider('eventNameProvider')]
    public function testTheEventNameIsWhatAFlowIsBoundTo(string $eventClass, string $expectedName): void
    {
        $event = new $eventClass(new Payment('tr_1'), $this->order(), $this->customer(), Context::createDefaultContext());

        $this->assertSame($expectedName, $event->getName());
    }

    /**
     * @return array<string, array{class-string<AbstractPaymentEvent>, string}>
     */
    public static function eventNameProvider(): array
    {
        return [
            'payment succeeded' => [SuccessEvent::class, 'mollie.payment.success'],
            'payment failed' => [FailedEvent::class, 'mollie.payment.failed'],
            'payment cancelled' => [CancelledEvent::class, 'mollie.payment.cancelled'],
        ];
    }

    public function testAFlowCanReachTheOrderTheCustomerAndThePayment(): void
    {
        $payment = new Payment('tr_1');
        $order = $this->order();
        $customer = $this->customer();

        $event = new SuccessEvent($payment, $order, $customer, Context::createDefaultContext());

        $this->assertSame($payment, $event->getPayment());
        $this->assertSame($order, $event->getOrder());
        $this->assertSame($customer, $event->getCustomer());
    }

    public function testTheIdsAFlowFiltersOnAreTakenFromTheOrderAndCustomer(): void
    {
        $event = new SuccessEvent(new Payment('tr_1'), $this->order(), $this->customer(), Context::createDefaultContext());

        $this->assertSame('tr_1', $event->getPaymentId());
        $this->assertSame('order-1', $event->getOrderId());
        $this->assertSame('customer-1', $event->getCustomerId());
        $this->assertSame('sales-channel-1', $event->getSalesChannelId());
    }

    public function testTheContextIsHandedOnSoTheFlowRunsInTheOrdersLanguage(): void
    {
        $context = Context::createDefaultContext();

        $event = new SuccessEvent(new Payment('tr_1'), $this->order(), $this->customer(), $context);

        $this->assertSame($context, $event->getContext());
    }

    public function testAMailFromTheFlowGoesToTheCustomer(): void
    {
        $event = new SuccessEvent(new Payment('tr_1'), $this->order(), $this->customer(), Context::createDefaultContext());

        $this->assertSame(['jane@shop.test' => 'Jane Doe'], $event->getMailStruct()->getRecipients());
    }

    public function testTheFlowBuilderOffersOrderCustomerAndPayment(): void
    {
        $available = SuccessEvent::getAvailableData()->toArray();

        $this->assertArrayHasKey('order', $available);
        $this->assertArrayHasKey('customer', $available);
        $this->assertArrayHasKey('payment', $available);
    }

    private function order(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setSalesChannelId('sales-channel-1');

        return $order;
    }

    private function customer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId('customer-1');
        $customer->setFirstName('Jane');
        $customer->setLastName('Doe');
        $customer->setEmail('jane@shop.test');

        return $customer;
    }
}
