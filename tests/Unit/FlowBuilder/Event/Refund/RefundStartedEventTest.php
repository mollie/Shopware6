<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Event\Refund;

use Mollie\Shopware\Component\FlowBuilder\Event\Refund\RefundStartedEvent;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

/**
 * The event a merchant hangs a "refund started" flow on - a mail to the customer is the usual one,
 * so the refunded amount has to reach the mail template as a value the flow can read.
 */
#[CoversClass(RefundStartedEvent::class)]
final class RefundStartedEventTest extends TestCase
{
    public function testTheEventNameIsWhatAFlowIsBoundTo(): void
    {
        $this->assertSame('mollie.refund.started', $this->event()->getName());
    }

    public function testTheRefundedAmountIsReadableInTheFlow(): void
    {
        $this->assertSame(['amount' => 24.5], $this->event()->getValues());
    }

    public function testAFlowCanReachTheOrderTheRefundAndTheAmount(): void
    {
        $event = $this->event();

        $this->assertSame('order-1', $event->getOrder()->getId());
        $this->assertSame('re_1', $event->getRefund()->getMollieRefundId());
        $this->assertSame(24.5, $event->getAmount());
    }

    public function testTheIdsAFlowFiltersOnAreTakenFromTheOrder(): void
    {
        $event = $this->event();

        $this->assertSame('order-1', $event->getOrderId());
        $this->assertSame('sales-channel-1', $event->getSalesChannelId());
    }

    public function testTheContextIsHandedOnSoTheFlowRunsInTheOrdersLanguage(): void
    {
        $context = Context::createDefaultContext();

        $this->assertSame($context, $this->event($context)->getContext());
    }

    public function testAMailFromTheFlowGoesToTheOrderCustomer(): void
    {
        $this->assertSame(['jane@shop.test' => 'Jane Doe'], $this->event()->getMailStruct()->getRecipients());
    }

    /**
     * A refund started from the admin can carry an order whose customer association was not
     * loaded. The flow must then send to nobody instead of failing the refund.
     */
    public function testAnOrderWithoutACustomerHasNoMailRecipient(): void
    {
        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setSalesChannelId('sales-channel-1');

        $event = new RefundStartedEvent($order, $this->refund(), 24.5, Context::createDefaultContext());

        $this->assertSame([], $event->getMailStruct()->getRecipients());
    }

    public function testTheFlowBuilderOffersOrderRefundAndAmount(): void
    {
        $available = RefundStartedEvent::getAvailableData()->toArray();

        $this->assertArrayHasKey('order', $available);
        $this->assertArrayHasKey('refund', $available);
        $this->assertArrayHasKey('amount', $available);
    }

    private function event(?Context $context = null): RefundStartedEvent
    {
        return new RefundStartedEvent($this->order(), $this->refund(), 24.5, $context ?? Context::createDefaultContext());
    }

    private function refund(): RefundEntity
    {
        $refund = new RefundEntity();
        $refund->setId('refund-1');
        $refund->setMollieRefundId('re_1');

        return $refund;
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
