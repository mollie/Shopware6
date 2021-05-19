<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Service;


use Kiener\MolliePayments\Service\TokenAnonymizer;
use PHPUnit\Framework\TestCase;

class TokenAnonymizerTest extends TestCase
{
    /**
     * This test verifies that expected constants are set
     *
     * @covers \Kiener\MolliePayments\Service\TokenAnonymizer
     */
    public function testConstants(): void
    {
        self::assertSame('*', TokenAnonymizer::TOKEN_ANONYMIZER_PLACEHOLDER_SYMBOL);
        self::assertSame(4, TokenAnonymizer::TOKEN_ANONYMIZER_PLACEHOLDER_COUNT);
        self::assertSame(15, TokenAnonymizer::TOKEN_ANONYMIZER_MAX_LENGTH);
    }

    /**
     * This test verifies that we get an empty string
     * if we have an empty string..
     *
     * @covers \Kiener\MolliePayments\Service\TokenAnonymizer
     */
    public function testAnonymizeEmpty()
    {
        $anonymizer = new TokenAnonymizer();
        $anonymized = $anonymizer->anonymize('');

        $this->assertEquals('', $anonymized);
    }

    /**
     * This test verifies that we get an empty string
     * if we have an invalid text with only spaces.
     *
     * @covers \Kiener\MolliePayments\Service\TokenAnonymizer
     */
    public function testAnonymizeSpaces()
    {
        $anonymizer = new TokenAnonymizer();
        $anonymized = $anonymizer->anonymize('   ');

        $this->assertEquals('', $anonymized);
    }

    /**
     * This test verifies we successfully anonymize the
     * last 4 digits of our provided string value.
     *
     * @covers \Kiener\MolliePayments\Service\TokenAnonymizer
     */
    public function testAnonymizeValue()
    {
        $anonymizer = new TokenAnonymizer();
        $anonymized = $anonymizer->anonymize('123456789');

        $this->assertEquals('12345****', $anonymized);
    }

    /**
     * This test verifies that if we have less than the
     * number of letters that should be anonymized, then we
     * make sure to see the first letter, and then add wildcards
     * with the number that we have provided.
     *
     * @covers \Kiener\MolliePayments\Service\TokenAnonymizer
     */
    public function testAnonymizeValueIsTooShort()
    {
        $anonymizer = new TokenAnonymizer();
        $anonymized = $anonymizer->anonymize('123');

        $this->assertEquals('1****', $anonymized);
    }

    /**
     * This test verifies that we correctly trim to the
     * provided max length of the anonmyized string
     *
     * @covers \Kiener\MolliePayments\Service\TokenAnonymizer
     */
    public function testMaxLength()
    {
        $anonymizer = new TokenAnonymizer();
        $anonymized = $anonymizer->anonymize('012345678901234567890');

        $this->assertEquals('01234567890****', $anonymized);
    }
}
