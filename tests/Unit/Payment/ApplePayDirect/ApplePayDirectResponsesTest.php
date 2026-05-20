<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ApplePayDirect;

use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\ApplePayDirectEnabledResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\CreateSessionResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetApplePayIdResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetCartResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetShippingMethodsResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\PayResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\SetShippingMethodResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayAmount;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayCart;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayShippingMethod;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;

#[CoversClass(ApplePayDirectEnabledResponse::class)]
#[CoversClass(CreateSessionResponse::class)]
#[CoversClass(GetApplePayIdResponse::class)]
#[CoversClass(GetCartResponse::class)]
#[CoversClass(GetShippingMethodsResponse::class)]
#[CoversClass(PayResponse::class)]
#[CoversClass(SetShippingMethodResponse::class)]
final class ApplePayDirectResponsesTest extends TestCase
{
    public function testApplePayDirectEnabledResponseEnabled(): void
    {
        $response = new ApplePayDirectEnabledResponse(true, 'pay-method-id');

        $this->assertTrue($response->isEnabled());
        $this->assertSame('pay-method-id', $response->getPaymentMethodId());
    }

    public function testApplePayDirectEnabledResponseDisabled(): void
    {
        $response = new ApplePayDirectEnabledResponse(false);

        $this->assertFalse($response->isEnabled());
        $this->assertNull($response->getPaymentMethodId());
    }

    public function testCreateSessionResponseStoresSession(): void
    {
        $session = ['merchantIdentifier' => 'merchant.com.example', 'domainName' => 'example.com'];

        $response = new CreateSessionResponse($session);

        $this->assertSame($session, $response->getSession());
    }

    public function testGetApplePayIdResponseWithId(): void
    {
        $response = new GetApplePayIdResponse('applepay-method-id');

        $this->assertSame('applepay-method-id', $response->getId());
    }

    public function testGetApplePayIdResponseWithNullId(): void
    {
        $response = new GetApplePayIdResponse(null);

        $this->assertNull($response->getId());
    }

    public function testGetShippingMethodsResponseStoresMethods(): void
    {
        $shippingMethod = new ApplePayShippingMethod('std', 'Standard', '3-5 days', new ApplePayAmount(4.90));
        $methods = [$shippingMethod];

        $response = new GetShippingMethodsResponse($methods);

        $this->assertSame($methods, $response->getShippingMethods());
    }

    public function testPayResponseStoresFields(): void
    {
        $salesChannelContext = new FakeSalesChannelContext('sc-1', 'token-1');

        $response = new PayResponse(true, 'https://example.com/redirect', 'OK', 'order-1', $salesChannelContext);

        $this->assertSame('https://example.com/redirect', $response->getRedirectUrl());
        $this->assertSame('order-1', $response->getOrderId());
        $this->assertSame($salesChannelContext, $response->getSalesChannelContext());
    }

    public function testGetCartResponseStoresCartAndShopwareCart(): void
    {
        $applePayCart = new ApplePayCart('Total', new ApplePayAmount(29.99));
        $shopwareCart = new Cart('cart-token-1');

        $response = new GetCartResponse($applePayCart, $shopwareCart);

        $this->assertSame($applePayCart, $response->getCart());
        $this->assertSame($shopwareCart, $response->getShopwareCart());
    }

    public function testSetShippingMethodResponseStoresContext(): void
    {
        $salesChannelContext = new FakeSalesChannelContext('sc-1', 'token-1');

        $response = new SetShippingMethodResponse($salesChannelContext);

        $this->assertSame($salesChannelContext, $response->getSalesChannelContext());
    }
}
