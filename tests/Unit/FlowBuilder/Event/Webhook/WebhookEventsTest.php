<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Event\Webhook;

use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusAuthorizedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusCancelledEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusChargebackEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusExpiredEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusFailedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusOpenEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPaidEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPartiallyRefundedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPendingEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusRefundedEvent;
use Mollie\Shopware\Component\Mollie\Payment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

/**
 * The event names are the legacy "All" / "status.<status>" scheme, so flows a merchant configured
 * before the refactor keep matching. Renaming one of them silently switches a live flow off.
 */
#[CoversClass(WebhookEvent::class)]
#[CoversClass(WebhookStatusPaidEvent::class)]
#[CoversClass(WebhookStatusAuthorizedEvent::class)]
#[CoversClass(WebhookStatusCancelledEvent::class)]
#[CoversClass(WebhookStatusChargebackEvent::class)]
#[CoversClass(WebhookStatusExpiredEvent::class)]
#[CoversClass(WebhookStatusFailedEvent::class)]
#[CoversClass(WebhookStatusOpenEvent::class)]
#[CoversClass(WebhookStatusPartiallyRefundedEvent::class)]
#[CoversClass(WebhookStatusPendingEvent::class)]
#[CoversClass(WebhookStatusRefundedEvent::class)]
final class WebhookEventsTest extends TestCase
{
    /**
     * @param class-string<WebhookEvent> $eventClass
     */
    #[DataProvider('eventNameProvider')]
    public function testTheEventNameIsWhatAFlowIsBoundTo(string $eventClass, string $expectedName): void
    {
        $event = new $eventClass(new Payment('tr_1'), $this->order(), Context::createDefaultContext());

        $this->assertSame($expectedName, $event->getName());
    }

    /**
     * @return array<string, array{class-string<WebhookEvent>, string}>
     */
    public static function eventNameProvider(): array
    {
        return [
            'any webhook' => [WebhookEvent::class, 'mollie.webhook_received.All'],
            'paid' => [WebhookStatusPaidEvent::class, 'mollie.webhook_received.status.paid'],
            'authorized' => [WebhookStatusAuthorizedEvent::class, 'mollie.webhook_received.status.authorized'],
            'cancelled' => [WebhookStatusCancelledEvent::class, 'mollie.webhook_received.status.canceled'],
            'chargeback' => [WebhookStatusChargebackEvent::class, 'mollie.webhook_received.status.chargeback'],
            'expired' => [WebhookStatusExpiredEvent::class, 'mollie.webhook_received.status.expired'],
            'failed' => [WebhookStatusFailedEvent::class, 'mollie.webhook_received.status.failed'],
            'open' => [WebhookStatusOpenEvent::class, 'mollie.webhook_received.status.open'],
            'partially refunded' => [WebhookStatusPartiallyRefundedEvent::class, 'mollie.webhook_received.status.partially_refunded'],
            'pending' => [WebhookStatusPendingEvent::class, 'mollie.webhook_received.status.pending'],
            'refunded' => [WebhookStatusRefundedEvent::class, 'mollie.webhook_received.status.refunded'],
        ];
    }

    public function testAFlowCanReachTheOrderAndThePayment(): void
    {
        $payment = new Payment('tr_1');
        $order = $this->order();

        $event = new WebhookStatusPaidEvent($payment, $order, Context::createDefaultContext());

        $this->assertSame($payment, $event->getPayment());
        $this->assertSame($order, $event->getOrder());
    }

    public function testTheIdsAFlowFiltersOnAreTakenFromTheOrderAndPayment(): void
    {
        $event = new WebhookStatusPaidEvent(new Payment('tr_1'), $this->order(), Context::createDefaultContext());

        $this->assertSame('tr_1', $event->getPaymentId());
        $this->assertSame('order-1', $event->getOrderId());
        $this->assertSame('sales-channel-1', $event->getSalesChannelId());
    }

    public function testTheContextIsHandedOnSoTheFlowRunsInTheOrdersLanguage(): void
    {
        $context = Context::createDefaultContext();

        $event = new WebhookStatusPaidEvent(new Payment('tr_1'), $this->order(), $context);

        $this->assertSame($context, $event->getContext());
    }

    public function testAMailFromTheFlowGoesToTheOrderCustomer(): void
    {
        $event = new WebhookStatusPaidEvent(new Payment('tr_1'), $this->order(), Context::createDefaultContext());

        $this->assertSame(['jane@shop.test' => 'Jane Doe'], $event->getMailStruct()->getRecipients());
    }

    /**
     * A webhook arrives without an order customer loaded when the association was not fetched. The
     * flow must then simply send to nobody instead of failing the whole webhook.
     */
    public function testAnOrderWithoutACustomerHasNoMailRecipient(): void
    {
        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setSalesChannelId('sales-channel-1');

        $event = new WebhookStatusPaidEvent(new Payment('tr_1'), $order, Context::createDefaultContext());

        $this->assertSame([], $event->getMailStruct()->getRecipients());
    }

    public function testTheFlowBuilderOffersOrderAndPayment(): void
    {
        $available = WebhookStatusPaidEvent::getAvailableData()->toArray();

        $this->assertArrayHasKey('order', $available);
        $this->assertArrayHasKey('payment', $available);
    }

    private function order(): OrderEntity
    {
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId('order-customer-1');
        $orderCustomer->setFirstName('Jane');
        $orderCustomer->setLastName('Doe');
        $orderCustomer->setEmail('jane@shop.test');

        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setSalesChannelId('sales-channel-1');
        $order->setOrderCustomer($orderCustomer);

        return $order;
    }
}
