<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service;

use Kiener\MolliePayments\Service\UrlParsingService;
use PHPUnit\Framework\TestCase;

class UrlParsingServiceTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        $this->service = new UrlParsingService();
    }

    /**
     * @dataProvider urlProvider
     */
    public function testIsUrl(string $url, bool $expected)
    {
        $this->assertEquals($expected, $this->service->isUrl($url));
    }

    public function urlProvider(): array
    {
        return [
            ['https://www.example.com', true],
            ['http://example.com', true],
            ['not a url', false],
            ['example.com', false],
        ];
    }

    /**
     * @dataProvider queryParameterProvider
     */
    public function testParseTrackingCodeQueryParameter(string $input, array $expected)
    {
        $this->assertEquals($expected, $this->service->parseTrackingCodeFromUrl($input));
    }

    public function queryParameterProvider(): array
    {
        return [
            ['https://www.example.com/product?code=12345&postal_code=123', ['code=12345&postal_code=123', 'https://www.example.com/product?%s']],
            ['https://www.example.com/product?shipment=abc123', ['shipment=abc123', 'https://www.example.com/product?%s']],
            ['https://www.example.com/product?track=track123', ['track=track123', 'https://www.example.com/product?%s']],
            ['https://www.example.com/product?tracking=track456', ['tracking=track456', 'https://www.example.com/product?%s']],
            ['https://www.example.com/tracking/?country=at&tracking_number=1023534500214250110508&postal_cod1e=1050', ['country=at&tracking_number=1023534500214250110508&postal_cod1e=1050', 'https://www.example.com/tracking/?%s']],
        ];
    }

    /**
     * @dataProvider hashProvider
     */
    public function testParseTrackingCodeHash(string $input, array $expected)
    {
        $this->assertEquals($expected, $this->service->parseTrackingCodeFromUrl($input));
    }

    public function hashProvider(): array
    {
        return [
            ['https://www.example.com/product#code=12345', ['code=12345', 'https://www.example.com/product#%s']],
            ['https://www.example.com/product#shipment=abc123', ['shipment=abc123', 'https://www.example.com/product#%s']],
            ['https://www.example.com/product#track=track123', ['track=track123', 'https://www.example.com/product#%s']],
            ['https://www.example.com/product#tracking=track456', ['tracking=track456', 'https://www.example.com/product#%s']],
        ];
    }

    /**
     * @dataProvider notFoundProvider
     */
    public function testParseTrackingCodeNotFound(string $input, array $expected)
    {
        $this->assertEquals($expected, $this->service->parseTrackingCodeFromUrl($input));
    }

    public function notFoundProvider(): array
    {
        return [
            ['https://www.example.com/product', ['https://www.example.com/product', '']],
            ['https://www.example.com/code/product', ['https://www.example.com/code/product', '']],
        ];
    }

    /**
     * @dataProvider encodePathAndQueryProvider
     */
    public function testEncodePathAndQuery(string $input, string $expected)
    {
        $this->assertEquals($expected, $this->service->encodePathAndQuery($input));
    }

    public function encodePathAndQueryProvider(): array
    {
        return [
            ['https://www.example.com/path/to/resource', 'https://www.example.com/path/to/resource'],
            ['https://www.example.com/path/to/{resource}', 'https://www.example.com/path/to/%7Bresource%7D'],
            ['https://www.example.com/path with spaces/to/resource', 'https://www.example.com/path%20with%20spaces/to/resource'],
            ['https://www.example.com/path/to/resource?query=123&test={test}', 'https://www.example.com/path/to/resource?query=123&test=%7Btest%7D'],
        ];
    }

    /**
     * @dataProvider sanitizeQueryProvider
     */
    public function testSanitizeQuery(array $input, array $expected)
    {
        $this->assertEquals($expected, $this->service->sanitizeQuery($input));
    }

    public function sanitizeQueryProvider(): array
    {
        return [
            [['key=value'], ['key=value']],
            [['key with spaces=value with spaces'], ['key%20with%20spaces=value%20with%20spaces']],
            [['key={value}'], ['key=%7Bvalue%7D']],
            [['key1=value1', 'key2=value2'], ['key1=value1', 'key2=value2']],
        ];
    }

    /**
     * @dataProvider sanitizeQueryPartProvider
     */
    public function testSanitizeQueryPart(string $input, string $expected)
    {
        $this->assertEquals($expected, $this->service->sanitizeQueryPart($input));
    }

    public function sanitizeQueryPartProvider(): array
    {
        return [
            ['key=value', 'key=value'],
            ['key with spaces=value with spaces', 'key%20with%20spaces=value%20with%20spaces'],
            ['key={value}', 'key=%7Bvalue%7D'],
            ['key', 'key'], // No '=' in the input, should return as is
        ];
    }
}
