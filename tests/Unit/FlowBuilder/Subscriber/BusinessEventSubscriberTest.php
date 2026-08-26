<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Subscriber;

use Mollie\Shopware\Component\FlowBuilder\Subscriber\BusinessEventSubscriber;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandler;
use Mollie\Shopware\Component\Subscription\SubscriptionDataService;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\FlowBuilder\Fake\FakeBusinessEventCollector;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGateway;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Shopware\Core\Framework\Event\BusinessEventCollectorResponse;
use Shopware\Core\Framework\Event\BusinessEventDefinition;

/**
 * Without this subscriber the plugin's events never show up in the Flow Builder dropdown, so a
 * merchant cannot build a flow on a Mollie payment at all.
 */
#[CoversClass(BusinessEventSubscriber::class)]
final class BusinessEventSubscriberTest extends TestCase
{
    public function testTheEventsAreAddedWhileShopwareCollectsThem(): void
    {
        $this->assertArrayHasKey(
            BusinessEventCollectorEvent::NAME,
            BusinessEventSubscriber::getSubscribedEvents()
        );
    }

    public function testThePaymentEventsAreOfferedInTheFlowBuilder(): void
    {
        $collection = $this->collect();

        $this->assertTrue($collection->has('mollie.payment.success'));
        $this->assertTrue($collection->has('mollie.payment.failed'));
        $this->assertTrue($collection->has('mollie.payment.cancelled'));
    }

    public function testTheRefundEventIsOfferedInTheFlowBuilder(): void
    {
        $this->assertTrue($this->collect()->has('mollie.refund.started'));
    }

    /**
     * Merchants configured flows on the per-status webhook events before the refactor; every one
     * of them has to keep showing up under its legacy name.
     */
    public function testEveryWebhookStatusEventIsOfferedInTheFlowBuilder(): void
    {
        $collection = $this->collect();

        $this->assertTrue($collection->has('mollie.webhook_received.All'));
        $this->assertTrue($collection->has('mollie.webhook_received.status.paid'));
        $this->assertTrue($collection->has('mollie.webhook_received.status.chargeback'));
        $this->assertTrue($collection->has('mollie.webhook_received.status.refunded'));
    }

    public function testTheSubscriptionEventsAreOfferedInTheFlowBuilder(): void
    {
        $collection = $this->collect();

        $this->assertTrue($collection->has('mollie.subscription.started'));
        $this->assertTrue($collection->has('mollie.subscription.ended'));
        $this->assertTrue($collection->has('mollie.subscription.renewed'));
    }

    /**
     * The events Shopware itself already collected must survive - the subscriber adds to the list,
     * it does not replace it.
     */
    public function testTheEventsShopwareAlreadyCollectedAreKept(): void
    {
        $collection = new BusinessEventCollectorResponse();
        $collection->set('checkout.order.placed', new BusinessEventDefinition('checkout.order.placed', \stdClass::class, []));

        $this->subscriber()->addEvents(new BusinessEventCollectorEvent($collection, Context::createDefaultContext()));

        $this->assertTrue($collection->has('checkout.order.placed'));
    }

    private function collect(): BusinessEventCollectorResponse
    {
        $collection = new BusinessEventCollectorResponse();

        $this->subscriber()->addEvents(new BusinessEventCollectorEvent($collection, Context::createDefaultContext()));

        return $collection;
    }

    private function subscriber(): BusinessEventSubscriber
    {
        $logger = new NullLogger();

        $actionHandler = new SubscriptionActionHandler(
            new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: true)),
            new FakeSubscriptionGateway(),
            new SubscriptionDataService(new FakeSubscriptionRepository(), $logger),
            [],
            new EventSpy(),
            $logger
        );

        return new BusinessEventSubscriber(new FakeBusinessEventCollector(), $actionHandler);
    }
}
