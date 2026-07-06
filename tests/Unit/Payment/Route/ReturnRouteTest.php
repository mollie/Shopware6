<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Route;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Route\ReturnRoute;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ReturnRoute::class)]
final class ReturnRouteTest extends TestCase
{
    public function testReturnRedirectsToFinalizeUrl(): void
    {
        $finalizeUrl = 'https://shop.example.com/payment/finalize-transaction?_sw_payment_token=token';

        $payment = new Payment('test');
        $payment->setFinalizeUrl($finalizeUrl);

        $route = new ReturnRoute(new FakeGateway('', $payment), new NullLogger());

        $response = $route->return('test', new Context(new SystemSource()));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame($finalizeUrl, $response->getTargetUrl());
    }
}
