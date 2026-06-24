<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\CreditCard;

use Mollie\Shopware\Component\Payment\CreditCard\StoreCreditCardTokenResponse;
use Mollie\Shopware\Component\Payment\CreditCard\StoreCreditCardTokenRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;

#[CoversClass(StoreCreditCardTokenRoute::class)]
#[CoversClass(StoreCreditCardTokenResponse::class)]
final class CreditCardRoutesTest extends TestCase
{
    public function testStoreCreditCardTokenIsSuccessful(): void
    {
        $route = new StoreCreditCardTokenRoute(new NullLogger());

        $response = $route->store('cust-1', 'card-token-1', Context::createDefaultContext());

        $this->assertInstanceOf(StoreCreditCardTokenResponse::class, $response);
        $this->assertTrue((bool) $response->getObject()->get('success'));
    }
}
