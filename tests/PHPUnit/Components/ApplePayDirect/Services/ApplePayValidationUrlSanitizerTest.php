<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\ApplePayDirect\Services;


use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayValidationUrlSanitizer;
use PHPUnit\Framework\TestCase;

class ApplePayValidationUrlSanitizerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->sanitizer = new ApplePayValidationUrlSanitizer();
    }

    /**
     * @dataProvider sanitationTestDataProvider
     */
    public function testProvidesSanitizedUrl(string $url, string $expected): void
    {
        $sanitizedUrl = $this->sanitizer->sanitizeValidationUrl($url);

        $this->assertEquals($expected, $sanitizedUrl);
    }

    public function sanitationTestDataProvider(): array
    {
        return [
            'keeps http value if provided' => ['http://example.com', 'http://example.com/'],
            'keeps https value if provided' => ['https://example.com', 'https://example.com/'],
            'adds https to beginning of string if missing' => ['example.com', 'https://example.com/'],
            'adds trailing slash if missing' => ['https://example.com', 'https://example.com/'],
            'adds https to beginning of string and trailing slash if missing' => ['example.com', 'https://example.com/'],
        ];
    }
}