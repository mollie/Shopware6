<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\PhoneNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhoneNumber::class)]
final class PhoneNumberTest extends TestCase
{
    #[DataProvider('phoneNumberProvider')]
    public function testToE164(string $phone, string $countryIso, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toE164($phone, $countryIso));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function phoneNumberProvider(): array
    {
        return [
            'already E.164' => ['+491711234567', 'DE', '+491711234567'],
            'E.164 with separators' => ['+49 171 123-45.67', 'DE', '+491711234567'],
            'E.164 with (0) infix' => ['+49 (0)171 1234567', 'DE', '+491711234567'],
            'international 00 prefix' => ['0049 171 1234567', 'DE', '+491711234567'],
            'national german mobile' => ['0171/1234567', 'DE', '+491711234567'],
            'national german landline' => ['030 123 45 67', 'DE', '+49301234567'],
            'national austrian mobile' => ['0664 123456', 'AT', '+43664123456'],
            'national dutch mobile' => ['06-12345678', 'NL', '+31612345678'],
            'national with lowercase iso' => ['0171 1234567', 'de', '+491711234567'],
            'national with unknown country' => ['0699 12345678', 'US', ''],
            'national with country without trunk prefix' => ['0612345678', 'ES', ''],
            'letters' => ['no phone', 'DE', ''],
            'letters after plus' => ['+49abc171', 'DE', ''],
            'too long' => ['+4917112345678901234', 'DE', ''],
            'only separators' => [' - / ', 'DE', ''],
            'empty' => ['', 'DE', ''],
        ];
    }

    public function testValidE164IsAccepted(): void
    {
        $this->assertTrue(PhoneNumber::isValidE164('+491711234567'));
    }

    public function testNationalFormatIsNotValidE164(): void
    {
        $this->assertFalse(PhoneNumber::isValidE164('0171 1234567'));
    }
}
