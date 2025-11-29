<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Components\PaypalExpress;

use Kiener\MolliePayments\Components\PaypalExpress\PaypalExpressException;
use Kiener\MolliePayments\Components\PaypalExpress\Route\CancelCheckoutRoute;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class CancelCheckoutRouteTest extends TestCase
{
    use PayPalExpressMockTrait;

    public function testGetDecoratedThrowsException(): void
    {
        $settingsService = $this->getSettings();
        $cartService = $this->getCartService();
        $paypalExpress = $this->getPaypalExpress();

        $route = new CancelCheckoutRoute($settingsService, $cartService, $paypalExpress);

        $this->expectException(DecorationPatternException::class);
        $route->getDecorated();
    }

    public function testPaymentIsNotEnabledExceptionIsThrown(): void
    {
        $settingsService = $this->getSettings();
        $cartService = $this->getCartService();
        $paypalExpress = $this->getPaypalExpress();

        $route = new CancelCheckoutRoute($settingsService, $cartService, $paypalExpress);

        try {
            $route->cancelCheckout($this->getContext());
        } catch (HttpException $e) {
            $this->assertSame($e->getErrorCode(), PaypalExpressException::PAYMENT_METHOD_DISABLED);
        }
    }

    public function testCartSessionIdIsMissing(): void
    {
        $settingsService = $this->getSettings(true);
        $cartService = $this->getCartService();
        $paypalExpress = $this->getPaypalExpress();

        $route = new CancelCheckoutRoute($settingsService, $cartService, $paypalExpress);

        try {
            $route->cancelCheckout($this->getContext());
        } catch (HttpException $e) {
            $this->assertSame($e->getErrorCode(), PaypalExpressException::MISSING_CART_SESSION_ID);
        }
    }

    public function testCancelCheckoutSuccess(): void
    {
        $settingsService = $this->getSettings(true);
        $cartService = $this->getCartService(true, true, true);
        $paypalExpress = $this->getPaypalExpress();

        $route = new CancelCheckoutRoute($settingsService, $cartService, $paypalExpress);

        $response = $route->cancelCheckout($this->getContext());
        $this->assertNotNull($response->getSessionId());
    }
}
