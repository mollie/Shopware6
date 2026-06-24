<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\PayPalExpress;

use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Payment\PayPalExpress\PaypalExpressException;
use Mollie\Shopware\Component\Payment\PayPalExpress\Route\CancelCheckoutResponse;
use Mollie\Shopware\Component\Payment\PayPalExpress\Route\CancelCheckoutRoute;
use Mollie\Shopware\Component\Payment\PayPalExpress\Route\FinishCheckoutResponse;
use Mollie\Shopware\Component\Payment\PayPalExpress\Route\FinishCheckoutRoute;
use Mollie\Shopware\Component\Payment\PayPalExpress\Route\StartCheckoutResponse;
use Mollie\Shopware\Component\Payment\PayPalExpress\Route\StartCheckoutRoute;
use Mollie\Shopware\Component\Settings\Struct\PayPalExpressSettings;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakeAccountService;
use Mollie\Shopware\Unit\Payment\Fake\FakeCartService;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodRepository;
use Mollie\Shopware\Unit\Payment\Fake\FakeSessionGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;

#[CoversClass(StartCheckoutRoute::class)]
#[CoversClass(CancelCheckoutRoute::class)]
#[CoversClass(FinishCheckoutRoute::class)]
final class PayPalExpressRoutesTest extends TestCase
{
    private FakeSalesChannelContext $salesChannelContext;

    public function setUp(): void
    {
        $this->salesChannelContext = new FakeSalesChannelContext('sc-1', 'cart-token-1');
    }

    // ------ StartCheckoutRoute ------

    public function testStartCheckoutIsSuccessful(): void
    {
        $session = $this->buildSession('session-1', 'https://mollie.com/checkout');
        $cart = $this->buildCartWithItems();

        $route = new StartCheckoutRoute(
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings(true)),
            new FakeSessionGateway($session),
            new FakeCartService($cart),
        );

        $response = $route->startCheckout(new \Symfony\Component\HttpFoundation\Request(), $this->salesChannelContext);

        $this->assertInstanceOf(StartCheckoutResponse::class, $response);
        $this->assertSame('session-1', $response->getSessionId());
        $this->assertSame('https://mollie.com/checkout', $response->getRedirectUrl());
    }

    public function testStartCheckoutThrowsWhenDisabled(): void
    {
        $route = new StartCheckoutRoute(
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings(false)),
            new FakeSessionGateway($this->buildSession('session-1')),
            new FakeCartService(new Cart('cart-token')),
        );

        try {
            $route->startCheckout(new \Symfony\Component\HttpFoundation\Request(), $this->salesChannelContext);
            $this->fail('Expected PaypalExpressException was not thrown');
        } catch (PaypalExpressException $exception) {
            $this->assertSame(PaypalExpressException::PAYMENT_METHOD_DISABLED, $exception->getErrorCode());
        }
    }

    public function testStartCheckoutThrowsWhenCartIsEmpty(): void
    {
        $route = new StartCheckoutRoute(
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings(true)),
            new FakeSessionGateway($this->buildSession('session-1')),
            new FakeCartService(new Cart('cart-token')),
        );

        try {
            $route->startCheckout(new \Symfony\Component\HttpFoundation\Request(), $this->salesChannelContext);
            $this->fail('Expected PaypalExpressException was not thrown');
        } catch (PaypalExpressException $exception) {
            $this->assertSame(PaypalExpressException::EMPTY_CART, $exception->getErrorCode());
        }
    }

    // ------ CancelCheckoutRoute ------

    public function testCancelCheckoutIsSuccessful(): void
    {
        $session = $this->buildSession('session-cancel-1');
        $cart = new Cart('cart-token');
        $cart->addExtension(Mollie::EXTENSION, $session);

        $route = new CancelCheckoutRoute(
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings(true)),
            new FakePaymentMethodRepository('paypal-express-method-id'),
            new FakeSessionGateway($session),
            new FakeCartService($cart),
        );

        $response = $route->cancel($this->salesChannelContext);

        $this->assertInstanceOf(CancelCheckoutResponse::class, $response);
        $this->assertSame('session-cancel-1', $response->getSessionId());
    }

    public function testCancelCheckoutThrowsWhenPaypalExpressIdIsNull(): void
    {
        $route = new CancelCheckoutRoute(
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings(true)),
            new FakePaymentMethodRepository(null),
            new FakeSessionGateway($this->buildSession('session-1')),
            new FakeCartService(new Cart('cart-token')),
        );

        try {
            $route->cancel($this->salesChannelContext);
            $this->fail('Expected PaypalExpressException was not thrown');
        } catch (PaypalExpressException $exception) {
            $this->assertSame(PaypalExpressException::PAYMENT_METHOD_DISABLED, $exception->getErrorCode());
        }
    }

    public function testCancelCheckoutThrowsWhenDisabled(): void
    {
        $route = new CancelCheckoutRoute(
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings(false)),
            new FakePaymentMethodRepository('paypal-express-method-id'),
            new FakeSessionGateway($this->buildSession('session-1')),
            new FakeCartService(new Cart('cart-token')),
        );

        try {
            $route->cancel($this->salesChannelContext);
            $this->fail('Expected PaypalExpressException was not thrown');
        } catch (PaypalExpressException $exception) {
            $this->assertSame(PaypalExpressException::PAYMENT_METHOD_DISABLED, $exception->getErrorCode());
        }
    }

    public function testCancelCheckoutThrowsWhenNoSessionInCart(): void
    {
        $route = new CancelCheckoutRoute(
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings(true)),
            new FakePaymentMethodRepository('paypal-express-method-id'),
            new FakeSessionGateway($this->buildSession('session-1')),
            new FakeCartService(new Cart('cart-token')),
        );

        try {
            $route->cancel($this->salesChannelContext);
            $this->fail('Expected PaypalExpressException was not thrown');
        } catch (PaypalExpressException $exception) {
            $this->assertSame(PaypalExpressException::MISSING_CART_SESSION_ID, $exception->getErrorCode());
        }
    }

    // ------ FinishCheckoutRoute ------

    public function testFinishCheckoutIsSuccessful(): void
    {
        $session = $this->buildSessionWithAddresses('session-finish-1', 'https://mollie.com/checkout', 'auth-id-1');

        $cart = new Cart('cart-token');
        $cart->addExtension(Mollie::EXTENSION, $session);

        $newContext = new FakeSalesChannelContext('sc-1', 'new-token-1');

        $route = new FinishCheckoutRoute(
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings(true)),
            new FakeSessionGateway($session),
            new FakeAccountService($newContext),
            new FakePaymentMethodRepository('paypal-express-method-id'),
            new FakeCartService($cart),
        );

        $response = $route->finishCheckout($this->salesChannelContext);

        $this->assertInstanceOf(FinishCheckoutResponse::class, $response);
        $this->assertSame('session-finish-1', $response->getSessionId());
        $this->assertSame('auth-id-1', $response->getAuthenticateId());
        $this->assertSame('new-token-1', $response->getContextToken());
    }

    public function testFinishCheckoutThrowsWhenPaypalExpressIdIsNull(): void
    {
        $route = new FinishCheckoutRoute(
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings(true)),
            new FakeSessionGateway($this->buildSession('s')),
            new FakeAccountService($this->salesChannelContext),
            new FakePaymentMethodRepository(null),
            new FakeCartService(new Cart('cart-token')),
        );

        try {
            $route->finishCheckout($this->salesChannelContext);
            $this->fail('Expected PaypalExpressException was not thrown');
        } catch (PaypalExpressException $exception) {
            $this->assertSame(PaypalExpressException::PAYMENT_METHOD_DISABLED, $exception->getErrorCode());
        }
    }

    public function testFinishCheckoutThrowsWhenDisabled(): void
    {
        $route = new FinishCheckoutRoute(
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings(false)),
            new FakeSessionGateway($this->buildSession('s')),
            new FakeAccountService($this->salesChannelContext),
            new FakePaymentMethodRepository('paypal-express-method-id'),
            new FakeCartService(new Cart('cart-token')),
        );

        try {
            $route->finishCheckout($this->salesChannelContext);
            $this->fail('Expected PaypalExpressException was not thrown');
        } catch (PaypalExpressException $exception) {
            $this->assertSame(PaypalExpressException::PAYMENT_METHOD_DISABLED, $exception->getErrorCode());
        }
    }

    public function testFinishCheckoutThrowsWhenNoSessionInCart(): void
    {
        $route = new FinishCheckoutRoute(
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings(true)),
            new FakeSessionGateway($this->buildSession('s')),
            new FakeAccountService($this->salesChannelContext),
            new FakePaymentMethodRepository('paypal-express-method-id'),
            new FakeCartService(new Cart('cart-token')),
        );

        try {
            $route->finishCheckout($this->salesChannelContext);
            $this->fail('Expected PaypalExpressException was not thrown');
        } catch (PaypalExpressException $exception) {
            $this->assertSame(PaypalExpressException::MISSING_CART_SESSION_ID, $exception->getErrorCode());
        }
    }

    public function testFinishCheckoutThrowsWhenBillingAddressMissing(): void
    {
        $session = $this->buildSession('s');
        $cart = new Cart('cart-token');
        $cart->addExtension(Mollie::EXTENSION, $session);

        $route = new FinishCheckoutRoute(
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings(true)),
            new FakeSessionGateway($session),
            new FakeAccountService($this->salesChannelContext),
            new FakePaymentMethodRepository('paypal-express-method-id'),
            new FakeCartService($cart),
        );

        try {
            $route->finishCheckout($this->salesChannelContext);
            $this->fail('Expected PaypalExpressException was not thrown');
        } catch (PaypalExpressException $exception) {
            $this->assertSame(PaypalExpressException::MISSING_BILLING_ADDRESS, $exception->getErrorCode());
        }
    }

    public function testFinishCheckoutThrowsWhenShippingAddressMissing(): void
    {
        $session = $this->buildSessionWithBillingOnly('s');
        $cart = new Cart('cart-token');
        $cart->addExtension(Mollie::EXTENSION, $session);

        $route = new FinishCheckoutRoute(
            new FakeSettingsService(paypalExpressSettings: new PayPalExpressSettings(true)),
            new FakeSessionGateway($session),
            new FakeAccountService($this->salesChannelContext),
            new FakePaymentMethodRepository('paypal-express-method-id'),
            new FakeCartService($cart),
        );

        try {
            $route->finishCheckout($this->salesChannelContext);
            $this->fail('Expected PaypalExpressException was not thrown');
        } catch (PaypalExpressException $exception) {
            $this->assertSame(PaypalExpressException::MISSING_SHIPPING_ADDRESS, $exception->getErrorCode());
        }
    }

    private function buildSession(string $id, string $redirectUrl = '', string $authenticationId = ''): Session
    {
        $session = new Session($id);
        $session->setRedirectUrl($redirectUrl);
        $session->setAuthenticationId($authenticationId);

        return $session;
    }

    private function buildSessionWithAddresses(string $id, string $redirectUrl = '', string $authenticationId = ''): Session
    {
        $addressData = [
            'email' => 'test@example.com',
            'givenName' => 'John',
            'familyName' => 'Doe',
            'streetAndNumber' => 'Main St 1',
            'streetAdditional' => '',
            'postalCode' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
        ];

        return Session::createFromClientResponse([
            'id' => $id,
            'authenticationId' => $authenticationId,
            '_links' => ['redirect' => ['href' => $redirectUrl]],
            'billingAddress' => $addressData,
            'shippingAddress' => $addressData,
        ]);
    }

    private function buildSessionWithBillingOnly(string $id): Session
    {
        $addressData = [
            'email' => 'test@example.com',
            'givenName' => 'John',
            'familyName' => 'Doe',
            'streetAndNumber' => 'Main St 1',
            'streetAdditional' => '',
            'postalCode' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
        ];

        return Session::createFromClientResponse([
            'id' => $id,
            'authenticationId' => '',
            '_links' => ['redirect' => ['href' => '']],
            'billingAddress' => $addressData,
        ]);
    }

    private function buildCartWithItems(): Cart
    {
        $cart = new Cart('cart-token');
        $lineItem = new LineItem('line-item-1', 'product', 'product-id-1', 1);
        $cart->add($lineItem);

        return $cart;
    }
}
