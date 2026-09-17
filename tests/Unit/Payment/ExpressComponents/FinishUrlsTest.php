<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\FinishUrls;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The pages a headless client wants the shopper on. They are named when the session is created,
 * where no order exists yet, so they may leave a placeholder for its id.
 */
#[CoversClass(FinishUrls::class)]
final class FinishUrlsTest extends TestCase
{
    public function testTheUrlsAreReadFromTheRequest(): void
    {
        $request = new Request([
            FinishUrls::FINISH_URL_PARAMETER => 'https://frontend.example/checkout/finish',
            FinishUrls::ERROR_URL_PARAMETER => 'https://frontend.example/checkout/failed',
        ]);

        $finishUrls = FinishUrls::fromRequest($request);

        $this->assertSame('https://frontend.example/checkout/finish', $finishUrls->getFinishUrlTemplate());
        $this->assertSame('https://frontend.example/checkout/failed', $finishUrls->getErrorUrlTemplate());
    }

    public function testAClientThatNamesNoUrlsGetsEmptyOnes(): void
    {
        $finishUrls = FinishUrls::fromRequest(new Request());

        $this->assertSame('', $finishUrls->getFinishUrlTemplate());
        $this->assertSame('', $finishUrls->getErrorUrlTemplate());
    }

    public function testTheOrderIdReplacesThePlaceholder(): void
    {
        $finishUrls = new FinishUrls(
            'https://frontend.example/checkout/finish/{orderId}',
            'https://frontend.example/checkout/failed/{orderId}'
        );

        $this->assertSame('https://frontend.example/checkout/finish/order-id', $finishUrls->getFinishUrl('order-id'));
        $this->assertSame('https://frontend.example/checkout/failed/order-id', $finishUrls->getErrorUrl('order-id'));
    }

    /**
     * The template survives the way to Mollie and back, so the placeholder must not be replaced
     * before the order exists.
     */
    public function testTheTemplateKeepsThePlaceholder(): void
    {
        $finishUrls = new FinishUrls('https://frontend.example/checkout/finish/{orderId}', '');

        $this->assertSame('https://frontend.example/checkout/finish/{orderId}', $finishUrls->getFinishUrlTemplate());
    }

    public function testAUrlWithoutAPlaceholderIsLeftAlone(): void
    {
        $finishUrls = new FinishUrls('https://frontend.example/checkout/finish', '');

        $this->assertSame('https://frontend.example/checkout/finish', $finishUrls->getFinishUrl('order-id'));
    }
}
