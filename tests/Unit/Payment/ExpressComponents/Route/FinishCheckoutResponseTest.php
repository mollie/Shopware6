<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Payment\ExpressComponents\Route\FinishCheckoutResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FinishCheckoutResponse::class)]
final class FinishCheckoutResponseTest extends TestCase
{
    public function testStoreApiPayloadCarriesTheFieldNamesTheStorefrontReads(): void
    {
        $response = new FinishCheckoutResponse('ses-1', 'context-token', 'order-1', '10001', 'https://mollie.test/redirect');

        $this->assertSame(
            [
                'sessionId' => 'ses-1',
                'token' => 'context-token',
                'orderId' => 'order-1',
                'orderNumber' => '10001',
                'redirectUrl' => 'https://mollie.test/redirect',
            ],
            $response->getObject()->all()
        );
    }
}
