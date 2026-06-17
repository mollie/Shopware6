<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Subscriber;

use Mollie\Shopware\Component\Payment\Action\Pay;
use Mollie\Shopware\Component\Payment\Subscriber\PendingOrderRedirectSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;

class PendingOrderRedirectSubscriberTest extends TestCase
{
    private const ORDER_ID = 'order-abc123';
    private const EDIT_ORDER_URL = '/account/order/edit/order-abc123';
    private PendingOrderRedirectSubscriber $subscriber;

    public function setUp(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn(self::EDIT_ORDER_URL);

        $this->subscriber = new PendingOrderRedirectSubscriber($router, new NullLogger());
    }

    /**
     * When Shopware redirects the customer from an empty cart to the order list
     * (browser-back scenario), the subscriber must redirect to the edit-order page.
     */
    public function testBrowserBackViaCheckoutRedirectsToEditOrderPage(): void
    {
        $session = $this->createSessionWithPendingOrder(self::ORDER_ID);
        $event = $this->createControllerEvent('frontend.account.order.page', $session);

        $this->subscriber->onController($event);

        $response = ($event->getController())();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(self::EDIT_ORDER_URL, $response->getTargetUrl());
    }

    /**
     * After the redirect the session key must be consumed.
     */
    public function testSessionKeyIsRemovedAfterRedirect(): void
    {
        $session = $this->createSessionWithPendingOrder(self::ORDER_ID);
        $event = $this->createControllerEvent('frontend.account.order.page', $session);

        $this->subscriber->onController($event);

        $this->assertNull($session->get(Pay::SESSION_KEY_PENDING_ORDER));
    }

    /**
     * Visiting the order list without a pending order must never redirect.
     */
    public function testNoRedirectWhenNoPendingOrderInSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $event = $this->createControllerEvent('frontend.account.order.page', $session);

        $originalController = $event->getController();

        $this->subscriber->onController($event);

        $this->assertSame($originalController, $event->getController());
    }

    /**
     * When the customer returns from Mollie (success or failure) the session
     * key must be cleared so no later redirect fires.
     */
    public function testSessionKeyIsClearedOnMollieReturn(): void
    {
        $session = $this->createSessionWithPendingOrder(self::ORDER_ID);
        $event = $this->createControllerEvent('frontend.mollie.payment', $session);

        $originalController = $event->getController();

        $this->subscriber->onController($event);

        $this->assertNull($session->get(Pay::SESSION_KEY_PENDING_ORDER));
        $this->assertSame($originalController, $event->getController());
    }

    /**
     * On unrelated routes the session key must remain untouched.
     */
    public function testUnrelatedRoutesLeaveSessionKeyIntact(): void
    {
        $session = $this->createSessionWithPendingOrder(self::ORDER_ID);
        $event = $this->createControllerEvent('frontend.home.page', $session);

        $originalController = $event->getController();

        $this->subscriber->onController($event);

        $this->assertSame($originalController, $event->getController());
        $this->assertSame(self::ORDER_ID, $session->get(Pay::SESSION_KEY_PENDING_ORDER));
    }

    // ------------------------------------------------------------------

    private function createSessionWithPendingOrder(string $orderId): Session
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set(Pay::SESSION_KEY_PENDING_ORDER, $orderId);

        return $session;
    }

    private function createControllerEvent(string $route, Session $session): ControllerEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        $request->setSession($session);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $dummyController = function () {
            return null;
        };

        return new ControllerEvent($kernel, $dummyController, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
