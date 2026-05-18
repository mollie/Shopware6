<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\PayPalExpress;

use Mollie\Shopware\Component\Payment\PayPalExpress\Route\CancelCheckoutResponse;
use Mollie\Shopware\Component\Payment\PayPalExpress\Route\FinishCheckoutResponse;
use Mollie\Shopware\Component\Payment\PayPalExpress\Route\StartCheckoutResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StartCheckoutResponse::class)]
#[CoversClass(FinishCheckoutResponse::class)]
#[CoversClass(CancelCheckoutResponse::class)]
final class PayPalExpressResponsesTest extends TestCase
{
    public function testStartCheckoutResponseStoresSessionIdAndRedirectUrl(): void
    {
        $response = new StartCheckoutResponse('session-abc', 'https://paypal.com/checkout');

        $this->assertSame('session-abc', $response->getSessionId());
        $this->assertSame('https://paypal.com/checkout', $response->getRedirectUrl());
    }

    public function testStartCheckoutResponseAllowsNullRedirectUrl(): void
    {
        $response = new StartCheckoutResponse('session-xyz', null);

        $this->assertSame('session-xyz', $response->getSessionId());
        $this->assertNull($response->getRedirectUrl());
    }

    public function testFinishCheckoutResponseStoresAllFields(): void
    {
        $response = new FinishCheckoutResponse('session-1', 'auth-id-1', 'ctx-token-1');

        $this->assertSame('session-1', $response->getSessionId());
        $this->assertSame('auth-id-1', $response->getAuthenticateId());
        $this->assertSame('ctx-token-1', $response->getContextToken());
    }

    public function testCancelCheckoutResponseStoresSessionId(): void
    {
        $response = new CancelCheckoutResponse('session-cancel-1');

        $this->assertSame('session-cancel-1', $response->getSessionId());
    }
}
