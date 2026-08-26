<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\PaymentLink\Subscriber;

use Mollie\Shopware\Component\PaymentLink\Controller\PaymentLinkController;
use Mollie\Shopware\Component\PaymentLink\Subscriber\TemporaryLoginSubscriber;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\PaymentLink\Fake\FakeLogoutRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPage;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * A payment link logs the order's customer in to show the finish page. Anyone who opens that link
 * must not stay logged into the customer's account afterwards.
 */
#[CoversClass(TemporaryLoginSubscriber::class)]
final class TemporaryLoginSubscriberTest extends TestCase
{
    public function testTheFinishPageIsWhatTriggersTheLogout(): void
    {
        $this->assertArrayHasKey(
            CheckoutFinishPageLoadedEvent::class,
            TemporaryLoginSubscriber::getSubscribedEvents()
        );
    }

    public function testACustomerLoggedInByAPaymentLinkIsLoggedOutAgain(): void
    {
        $logoutRoute = new FakeLogoutRoute();
        $session = $this->session(temporaryLogin: true);

        $this->subscriber($logoutRoute)->onFinishPageLoaded($this->event($session));

        $this->assertSame(['token-1'], $logoutRoute->getLoggedOutTokens());
    }

    /**
     * A customer who logged in themselves and just finished a normal checkout has to stay logged in.
     */
    public function testACustomerWhoLoggedInThemselvesStaysLoggedIn(): void
    {
        $logoutRoute = new FakeLogoutRoute();
        $session = $this->session(temporaryLogin: false);

        $this->subscriber($logoutRoute)->onFinishPageLoaded($this->event($session));

        $this->assertSame([], $logoutRoute->getLoggedOutTokens());
    }

    /**
     * The marker is cleared with the logout, so reloading the finish page does not log the next
     * customer out of their own session.
     */
    public function testTheMarkerIsClearedSoASecondLoadDoesNotLogOutAgain(): void
    {
        $logoutRoute = new FakeLogoutRoute();
        $session = $this->session(temporaryLogin: true);
        $subscriber = $this->subscriber($logoutRoute);

        $subscriber->onFinishPageLoaded($this->event($session));
        $subscriber->onFinishPageLoaded($this->event($session));

        $this->assertSame(['token-1'], $logoutRoute->getLoggedOutTokens());
    }

    private function session(bool $temporaryLogin): Session
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set(PaymentLinkController::TEMPORARY_LOGIN_SESSION_KEY, $temporaryLogin);

        return $session;
    }

    private function event(Session $session): CheckoutFinishPageLoadedEvent
    {
        $request = new Request();
        $request->setSession($session);

        return new CheckoutFinishPageLoadedEvent(
            new CheckoutFinishPage(),
            new FakeSalesChannelContext('sales-channel-1', 'token-1'),
            $request
        );
    }

    private function subscriber(FakeLogoutRoute $logoutRoute): TemporaryLoginSubscriber
    {
        return new TemporaryLoginSubscriber($logoutRoute, new NullLogger());
    }
}
