<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service;

use Kiener\MolliePayments\Service\TokenAnonymizer;
use PHPUnit\Framework\TestCase;

class TokenAnonymizerTest extends TestCase
{
    public function testConstants(): void
    {
        self::assertSame('*', TokenAnonymizer::TOKEN_ANONYMIZER_PLACEHOLDER_SYMBOL);
        self::assertSame(4, TokenAnonymizer::TOKEN_ANONYMIZER_PLACEHOLDER_COUNT);
        self::assertSame(15, TokenAnonymizer::TOKEN_ANONYMIZER_MAX_LENGTH);
    }

    /**
     * function tests that token is anoymized as expected
     *
     * @param string $expected
     * @param string $token
     * @dataProvider getTestData
     */
    public function testAnonymize(string $expected, string $token): void
    {
        $anonymizer = new TokenAnonymizer();

        self::assertSame($expected, $anonymizer->anonymize($token));
    }

    public function getTestData(): array
    {
        return [
            'test that empty string returns empty string' => ['', '   '],
            'anonymize short min char token' => ['t','t****'],
            'anonymize short min char token' => ['tes','tes****'],
        ];
    }
}
