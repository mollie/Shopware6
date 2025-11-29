<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Service;

use Kiener\MolliePayments\Service\TokenAnonymizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TokenAnonymizerTest extends TestCase
{
    public function testConstants(): void
    {
        self::assertSame('*', TokenAnonymizer::TOKEN_ANONYMIZER_PLACEHOLDER_SYMBOL);
        self::assertSame(4, TokenAnonymizer::TOKEN_ANONYMIZER_PLACEHOLDER_COUNT);
        self::assertSame(4, TokenAnonymizer::TOKEN_ANONYMIZER_COUNT_LAST_CHARACTERS);
        self::assertSame(3, TokenAnonymizer::TOKEN_ANONYMIZER_COUNT_FIRST_CHARACTERS);
    }

    /**
     * function tests that token is anoymized as expected
     */
    #[DataProvider('getTestData')]
    public function testAnonymize(string $expected, string $token): void
    {
        $anonymizer = new TokenAnonymizer();

        self::assertSame($expected, $anonymizer->anonymize($token));
    }

    public static function getTestData(): array
    {
        return [
            'test that empty string returns empty string' => ['', '   '],
            'anonymize short min char token' => ['t****', 't'],
            'anonymize short char token' => ['t****', 'tes'],
            'anonymize standard way' => ['ord****obar', 'ord_foobar'],
        ];
    }
}
