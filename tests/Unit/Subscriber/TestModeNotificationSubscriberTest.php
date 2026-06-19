<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscriber;

use Mollie\Shopware\Subscriber\TestModeNotificationSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;

#[CoversClass(TestModeNotificationSubscriber::class)]
final class TestModeNotificationSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        $events = TestModeNotificationSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(AccountOverviewPageLoadedEvent::class, $events);
        $this->assertArrayHasKey(AccountEditOrderPageLoadedEvent::class, $events);
        $this->assertArrayHasKey(CheckoutConfirmPageLoadedEvent::class, $events);
        $this->assertArrayHasKey(CheckoutFinishPageLoadedEvent::class, $events);
    }
}
