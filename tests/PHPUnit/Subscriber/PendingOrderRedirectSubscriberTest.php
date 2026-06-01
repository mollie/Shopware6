<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Subscriber;

use Kiener\MolliePayments\Subscriber\PendingOrderRedirectSubscriber;
use Mollie\Shopware\Component\Payment\PayAction;
use PHPUnit\Framework\TestCase;
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

        $this->subscriber = new PendingOrderRedirectSubscriber($router);
    }

    /**
     * This test verifies that when the customer returns from Mollie
     * (frontend.mollie.payment), the pending order session key is cleared
     * and no redirect is triggered. Without this, a successful payment leaves
     * a stale session key that later causes unexpected redirects.
     */
    public function testSessionKeyIsClearedOnMollieReturnRoute(): void
    {
        $session = $this->createSessionWithPendingOrder(self::ORDER_ID);
        $event = $this->createControllerEvent('frontend.mollie.payment', $session);

        $originalController = $event->getController();

        $this->subscriber->onController($event);

        $this->assertNull($session->get(PayAction::SESSION_KEY_PENDING_ORDER));
        $this->assertSame($originalController, $event->getController());
    }

    /**
     * This test verifies that when the customer hits the browser back button
     * after checkout (landing on the account order list), they are redirected
     * to the edit-order page for the pending order.
     */
    public function testBrowserBackRedirectsToEditOrderPage(): void
    {
        $session = $this->createSessionWithPendingOrder(self::ORDER_ID);
        $event = $this->createControllerEvent('frontend.account.order.page', $session);

        $this->subscriber->onController($event);

        $response = ($event->getController())();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(self::EDIT_ORDER_URL, $response->getTargetUrl());
    }

    /**
     * This test verifies that after the browser-back redirect the session key
     * is removed so that subsequent visits to the account order page are unaffected.
     */
    public function testSessionKeyIsRemovedAfterBrowserBackRedirect(): void
    {
        $session = $this->createSessionWithPendingOrder(self::ORDER_ID);
        $event = $this->createControllerEvent('frontend.account.order.page', $session);

        $this->subscriber->onController($event);

        $this->assertNull($session->get(PayAction::SESSION_KEY_PENDING_ORDER));
    }

    /**
     * This test verifies that visiting the account order list without a pending
     * order in the session does not trigger any redirect.
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
     * This test verifies that on unrelated routes the subscriber leaves the
     * controller and the session completely untouched.
     */
    public function testUnrelatedRoutesAreIgnored(): void
    {
        $session = $this->createSessionWithPendingOrder(self::ORDER_ID);
        $event = $this->createControllerEvent('frontend.home.page', $session);

        $originalController = $event->getController();

        $this->subscriber->onController($event);

        $this->assertSame($originalController, $event->getController());
        $this->assertSame(self::ORDER_ID, $session->get(PayAction::SESSION_KEY_PENDING_ORDER));
    }

    // ------------------------------------------------------------------

    private function createSessionWithPendingOrder(string $orderId): Session
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set(PayAction::SESSION_KEY_PENDING_ORDER, $orderId);

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
