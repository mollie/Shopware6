<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Components\PaypalExpress;

use Kiener\MolliePayments\Components\PaypalExpress\PaypalExpressException;
use Kiener\MolliePayments\Components\PaypalExpress\Route\FinishCheckoutRoute;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Struct\ArrayStruct;

class FinishCheckoutRouteTest extends TestCase
{
    use PayPalExpressMockTrait;

    public function testGetDecoratedThrowsException(): void
    {
        $settingsService = $this->getSettings();
        $cartService = $this->getCartService();
        $paypalExpress = $this->getPaypalExpress();

        $route = new FinishCheckoutRoute($settingsService, $cartService, $paypalExpress);

        $this->expectException(DecorationPatternException::class);
        $route->getDecorated();
    }

    public function testPaymentIsNotEnabledExceptionIsThrown(): void
    {
        $settingsService = $this->getSettings();
        $cartService = $this->getCartService();
        $paypalExpress = $this->getPaypalExpress();

        $route = new FinishCheckoutRoute($settingsService, $cartService, $paypalExpress);

        try {
            $route->finishCheckout($this->getContext());
        } catch (HttpException $e) {
            $this->assertSame($e->getErrorCode(), PaypalExpressException::PAYMENT_METHOD_DISABLED);
        }
    }

    public function testCartSessionIdIsMissing(): void
    {
        $settingsService = $this->getSettings(true);
        $cartService = $this->getCartService();
        $paypalExpress = $this->getPaypalExpress();

        $route = new FinishCheckoutRoute($settingsService, $cartService, $paypalExpress);

        try {
            $route->finishCheckout($this->getContext());
        } catch (HttpException $e) {
            $this->assertSame($e->getErrorCode(), PaypalExpressException::MISSING_CART_SESSION_ID);
        }
    }

    public function testSessionShippingAddressIsMissing(): void
    {
        $settingsService = $this->getSettings(true);
        $cartService = $this->getCartService(true, true, true);
        $paypalExpress = $this->getPaypalExpress(true, false, true);

        $route = new FinishCheckoutRoute($settingsService, $cartService, $paypalExpress);

        try {
            $route->finishCheckout($this->getContext());
        } catch (HttpException $e) {
            $this->assertSame($e->getErrorCode(), PaypalExpressException::MISSING_SHIPPING_ADDRESS);
        }
    }

    public function testSessionBillingAddressIsMissing(): void
    {
        $settingsService = $this->getSettings(true);
        $cartService = $this->getCartService(true, true, true);
        $methodDetails = new \stdClass();
        $methodDetails->shippingAddress = new \stdClass();

        $paypalExpress = $this->getPaypalExpress(true, false, true, false, false, $methodDetails);

        $route = new FinishCheckoutRoute($settingsService, $cartService, $paypalExpress);

        try {
            $route->finishCheckout($this->getContext());
        } catch (HttpException $e) {
            $this->assertSame($e->getErrorCode(), PaypalExpressException::MISSING_BILLING_ADDRESS);
        }
    }

    public function testBillingAddressParsingError(): void
    {
        $settingsService = $this->getSettings(true);
        $cartService = $this->getCartService(true, true, true);
        $methodDetails = new \stdClass();
        $methodDetails->shippingAddress = new \stdClass();
        $billingAddress = new \stdClass();
        $billingAddress->streetAndNumber = 'fake 10';
        $billingAddress->streetAdditional = 'B';
        $billingAddress->phone = '01235';
        $billingAddress->email = 'fake@fake';
        $methodDetails->billingAddress = $billingAddress;

        $paypalExpress = $this->getPaypalExpress(true, false, true, false, true, $methodDetails);

        $route = new FinishCheckoutRoute($settingsService, $cartService, $paypalExpress);

        try {
            $route->finishCheckout($this->getContext());
        } catch (HttpException $e) {
            $this->assertSame($e->getErrorCode(), PaypalExpressException::BILLING_ADDRESS_PARSING_ERROR);
        }
    }

    public function testShippingAddressParsingError(): void
    {
        $settingsService = $this->getSettings(true);
        $cartService = $this->getCartService(true, true, true);
        $methodDetails = new \stdClass();
        $methodDetails->shippingAddress = new \stdClass();
        $billingAddress = new \stdClass();
        $billingAddress->streetAndNumber = 'fake 10';

        $billingAddress->streetAdditional = 'B';
        $billingAddress->phone = '01235';
        $billingAddress->email = 'fake@fake';
        $billingAddress->givenName = 'test';
        $billingAddress->familyName = 'test';
        $billingAddress->postalCode = '01234';
        $billingAddress->city = 'test';
        $billingAddress->country = 'test';
        $methodDetails->billingAddress = $billingAddress;

        $paypalExpress = $this->getPaypalExpress(true, false, true, false, false, $methodDetails);

        $route = new FinishCheckoutRoute($settingsService, $cartService, $paypalExpress);

        try {
            $route->finishCheckout($this->getContext());
        } catch (HttpException $e) {
            $this->assertSame($e->getErrorCode(), PaypalExpressException::SHIPPING_ADDRESS_PARSING_ERROR);
        }
    }

    public function testAuthenticateIdSetInCartExtension(): void
    {
        $settingsService = $this->getSettings(true, true);
        $cartService = $this->getCartService(true, true, true);
        $methodDetails = new \stdClass();
        $shippingAddress = new \stdClass();
        $shippingAddress->givenName = 'test';
        $shippingAddress->familyName = 'test';
        $shippingAddress->streetAndNumber = 'Fakestreet 10';
        $shippingAddress->postalCode = '12356';
        $shippingAddress->city = 'test';
        $shippingAddress->country = 'test';
        $methodDetails->shippingAddress = $shippingAddress;
        $billingAddress = new \stdClass();
        $billingAddress->streetAndNumber = 'fake 10';

        $billingAddress->streetAdditional = 'B';
        $billingAddress->phone = '01235';
        $billingAddress->email = 'fake@fake';
        $billingAddress->givenName = 'test';
        $billingAddress->familyName = 'test';
        $billingAddress->postalCode = '01234';
        $billingAddress->city = 'test';
        $billingAddress->country = 'test';
        $methodDetails->billingAddress = $billingAddress;

        $paypalExpress = $this->getPaypalExpress(true, false, true, false, true, $methodDetails);

        $route = new FinishCheckoutRoute($settingsService, $cartService, $paypalExpress);
        $response = $route->finishCheckout($this->getContext());
        $cartExtension = $this->cart->getExtension(CustomFieldsInterface::MOLLIE_KEY);

        $this->assertNotNull($response->getSessionId());
        $this->assertNotNull($response->getAuthenticateId());

        $this->assertInstanceOf(ArrayStruct::class, $cartExtension);
        $this->assertSame('fakeAuthenticationId', $cartExtension[CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID]);
    }
}
