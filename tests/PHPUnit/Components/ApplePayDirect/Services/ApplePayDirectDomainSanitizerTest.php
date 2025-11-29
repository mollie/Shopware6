<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\ApplePayDirect\Services;

use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayDirectDomainSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ApplePayDirectDomainSanitizerTest extends TestCase
{
    private ApplePayDirectDomainSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new ApplePayDirectDomainSanitizer();
    }

    #[DataProvider('sanitationTestDataProvider')]
    public function testProvidesSanitizedDomain(string $url, string $expected): void
    {
        $sanitizedUrl = $this->sanitizer->sanitizeDomain($url);

        $this->assertEquals($expected, $sanitizedUrl);
    }

    public static function sanitationTestDataProvider(): array
    {
        return [
            'removes http value if provided' => ['http://example.com', 'example.com'],
            'removes https value if provided' => ['https://example.com', 'example.com'],
            'removes trailing slash if provided' => ['https://example.com/', 'example.com'],
            'removes https at beginning of string and trailing slash if provided' => ['https://example.com/', 'example.com'],
            'removes shop language slug if provided' => ['https://example.com/shop/de', 'example.com'],
            'removes shop language slug and trailing slash if provided' => ['example.com/shop/de/', 'example.com'],
            'removes shop language slug and trailing slash if provided and protocol' => ['https://example.com/shop/de/', 'example.com'],
            'sub domains are not removed' => ['sub.example.com', 'sub.example.com'],
        ];
    }
}
