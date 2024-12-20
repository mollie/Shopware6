<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\PaypalExpress;

use Kiener\MolliePayments\Components\PaypalExpress\PaypalExpressException;
use Kiener\MolliePayments\Components\PaypalExpress\Route\StartCheckoutRoute;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\HttpFoundation\Request;

/**
 * @final
 */
class StartCheckoutRouteTest extends TestCase
{
    use PayPalExpressMockTrait;

    public function testGetDecoratedThrowsException(): void
    {
        $settingsService = $this->getSettings();
        $cartService = $this->getCartService();
        $paypalExpress = $this->getPaypalExpress();

        $route = new StartCheckoutRoute($settingsService, $cartService, $paypalExpress);

        $this->expectException(DecorationPatternException::class);
        $route->getDecorated();
    }

    public function testPaymentIsNotEnabledExceptionIsThrown(): void
    {
        $settingsService = $this->getSettings();
        $cartService = $this->getCartService();
        $paypalExpress = $this->getPaypalExpress();


        $route = new StartCheckoutRoute($settingsService, $cartService, $paypalExpress);
        $request = new Request();


        try {
            $route->startCheckout($request, $this->getContext());
        } catch (HttpException $e) {
            $this->assertSame($e->getErrorCode(), PaypalExpressException::PAYMENT_METHOD_DISABLED);
        }
    }

    public function testCartIsEmptyExceptionIsThrown(): void
    {
        $settingsService = $this->getSettings(true);
        $cartService = $this->getCartService(true);

        $paypalExpress = $this->getPaypalExpress();


        $route = new StartCheckoutRoute($settingsService, $cartService, $paypalExpress);
        $request = new Request();

        try {
            $route->startCheckout($request, $this->getContext());
        } catch (HttpException $e) {
            $this->assertSame($e->getErrorCode(), PaypalExpressException::EMPTY_CART);
        }
    }

    public function testStartSessionReturnsEmptyId(): void
    {
        $settingsService = $this->getSettings(true);
        $cartService = $this->getCartService(true, true);

        $paypalExpress = $this->getPaypalExpress(false, true);


        $route = new StartCheckoutRoute($settingsService, $cartService, $paypalExpress);
        $request = new Request();

        try {
            $route->startCheckout($request, $this->getContext());
        } catch (HttpException $e) {
            $this->assertSame($e->getErrorCode(), PaypalExpressException::MISSING_SESSION_ID);
        }
    }

    public function testLoadSessionReturnsEmptyId(): void
    {
        $settingsService = $this->getSettings(true);
        $cartService = $this->getCartService(true, true, true);

        $paypalExpress = $this->getPaypalExpress(false, false, true);


        $route = new StartCheckoutRoute($settingsService, $cartService, $paypalExpress);
        $request = new Request();

        try {
            $route->startCheckout($request, $this->getContext());
        } catch (HttpException $e) {
            $this->assertSame($e->getErrorCode(), PaypalExpressException::MISSING_SESSION_ID);
        }
    }

    public function testStartSessionIdStoresCartExtension(): void
    {
        $settingsService = $this->getSettings(true);
        $cartService = $this->getCartService(true, true);

        $paypalExpress = $this->getPaypalExpress(true, true);


        $route = new StartCheckoutRoute($settingsService, $cartService, $paypalExpress);
        $request = new Request();

        $response = $route->startCheckout($request, $this->getContext());

        $this->assertNotNull($response->getSessionId());
        $this->assertSame('fakeSessionId', $this->cart->getExtension(CustomFieldsInterface::MOLLIE_KEY)[CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY]);
    }

    public function testLoadSessionIdStoresCartExtension(): void
    {
        $settingsService = $this->getSettings(true);
        $cartService = $this->getCartService(true, true, true);

        $paypalExpress = $this->getPaypalExpress(true, false, true);


        $route = new StartCheckoutRoute($settingsService, $cartService, $paypalExpress);
        $request = new Request();

        $response = $route->startCheckout($request, $this->getContext());

        $this->assertNotNull($response->getSessionId());
        $this->assertSame('fakeSessionId', $this->cart->getExtension(CustomFieldsInterface::MOLLIE_KEY)[CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY]);
    }

    public function testDataProtectionFlagIsSet(): void
    {
        $settingsService = $this->getSettings(true, true);
        $cartService = $this->getCartService(true, true, true);

        $paypalExpress = $this->getPaypalExpress(true, false, true);


        $route = new StartCheckoutRoute($settingsService, $cartService, $paypalExpress);
        $request = new Request();
        $request->request->set(CustomFieldsInterface::ACCEPTED_DATA_PROTECTION, true);

        $response = $route->startCheckout($request, $this->getContext());

        $cartExtension = $this->cart->getExtension(CustomFieldsInterface::MOLLIE_KEY);
        $this->assertInstanceOf(ArrayStruct::class, $cartExtension);
        $this->assertNotNull($response->getSessionId());
        $this->assertSame('fakeSessionId', $cartExtension[CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY]);
        $this->assertSame(1, $cartExtension[CustomFieldsInterface::ACCEPTED_DATA_PROTECTION]);
    }
}
