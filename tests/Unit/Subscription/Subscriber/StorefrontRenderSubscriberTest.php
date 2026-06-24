<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Subscriber;

use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Subscriber\StorefrontRenderSubscriber;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(StorefrontRenderSubscriber::class)]
final class StorefrontRenderSubscriberTest extends TestCase
{
    public function testSetsTwigParametersFromEnabledSettings(): void
    {
        $event = $this->buildEvent();
        $subscriber = new StorefrontRenderSubscriber(
            new FakeSettingsService(
                subscriptionSettings: new SubscriptionSettings(enabled: true, showIndicator: true)
            )
        );

        $subscriber->onStorefrontRender($event);

        $this->assertTrue($event->getParameter('mollie_subscriptions_enabled'));
        $this->assertTrue($event->getParameter('mollie_subscriptions_show_indicator'));
    }

    public function testSetsTwigParametersFromDisabledSettings(): void
    {
        $event = $this->buildEvent();
        $subscriber = new StorefrontRenderSubscriber(
            new FakeSettingsService(
                subscriptionSettings: new SubscriptionSettings(enabled: false, showIndicator: false)
            )
        );

        $subscriber->onStorefrontRender($event);

        $this->assertFalse($event->getParameter('mollie_subscriptions_enabled'));
        $this->assertFalse($event->getParameter('mollie_subscriptions_show_indicator'));
    }

    private function buildEvent(): StorefrontRenderEvent
    {
        return new StorefrontRenderEvent(
            'view.html.twig',
            [],
            new Request(),
            new FakeSalesChannelContext()
        );
    }
}
