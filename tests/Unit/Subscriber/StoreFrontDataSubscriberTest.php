<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscriber;

use Mollie\Shopware\Subscriber\StoreFrontDataSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;

#[CoversClass(StoreFrontDataSubscriber::class)]
final class StoreFrontDataSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        $events = StoreFrontDataSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(CheckoutConfirmPageLoadedEvent::class, $events);
        $this->assertArrayHasKey(AccountEditOrderPageLoadedEvent::class, $events);
    }
}
