<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Event\Webhook;

use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusAuthorizedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusCancelledEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusExpiredEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusFailedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusOpenEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPaidEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPendingEvent;
use Mollie\Shopware\Component\Mollie\Payment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

#[CoversClass(WebhookStatusPaidEvent::class)]
#[CoversClass(WebhookStatusOpenEvent::class)]
#[CoversClass(WebhookStatusPendingEvent::class)]
#[CoversClass(WebhookStatusAuthorizedEvent::class)]
#[CoversClass(WebhookStatusCancelledEvent::class)]
#[CoversClass(WebhookStatusExpiredEvent::class)]
#[CoversClass(WebhookStatusFailedEvent::class)]
final class WebhookEventsTest extends TestCase
{
    /**
     * WebhookEvent::getName() uses self::getStatus() (not static::), so all subclasses return the
     * base-class name 'mollie.webhook_received.all'. The per-status getStatus() override is used
     * only when calling getStatus() directly on the concrete class.
     */
    public function testAllWebhookEventsReturnBaseNameFromGetName(): void
    {
        $classes = [
            WebhookStatusPaidEvent::class,
            WebhookStatusOpenEvent::class,
            WebhookStatusPendingEvent::class,
            WebhookStatusAuthorizedEvent::class,
            WebhookStatusCancelledEvent::class,
            WebhookStatusExpiredEvent::class,
            WebhookStatusFailedEvent::class,
        ];

        foreach ($classes as $class) {
            $event = $this->buildEvent($class);
            $this->assertSame('mollie.webhook_received.all', $event->getName(), "Expected base name for {$class}");
        }
    }

    public function testGetters(): void
    {
        $payment = new Payment('tr_test123');
        $order = new OrderEntity();
        $order->setId('order-abc');
        $order->setSalesChannelId('sc-xyz');
        $context = new Context(new SystemSource());

        $event = new WebhookStatusPaidEvent($payment, $order, $context);

        $this->assertSame($payment, $event->getPayment());
        $this->assertSame('tr_test123', $event->getPaymentId());
        $this->assertSame('order-abc', $event->getOrderId());
        $this->assertSame('sc-xyz', $event->getSalesChannelId());
        $this->assertSame($order, $event->getOrder());
        $this->assertSame($context, $event->getContext());
    }

    public function testGetMailStructWithNullOrderCustomer(): void
    {
        $payment = new Payment('tr_test123');
        $order = new OrderEntity();
        $order->setId('order-abc');
        $context = new Context(new SystemSource());

        $event = new WebhookStatusPaidEvent($payment, $order, $context);

        $mailStruct = $event->getMailStruct();
        $this->assertEmpty($mailStruct->getRecipients());
    }

    public function testGetAvailableData(): void
    {
        $collection = WebhookStatusPaidEvent::getAvailableData();

        $this->assertNotNull($collection);
    }

    /**
     * @template T of \Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookEvent
     * @param class-string<T> $className
     * @return T
     */
    private function buildEvent(string $className): mixed
    {
        $payment = new Payment('tr_test');
        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setSalesChannelId('sc-1');
        $context = new Context(new SystemSource());

        return new $className($payment, $order, $context);
    }
}
