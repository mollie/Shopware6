<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Service;

use Kiener\MolliePayments\Service\ApplePayDomainVerificationService;
use PHPUnit\Framework\TestCase;

class ApplePayDomainVerificationServiceTest extends TestCase
{
    private const URL_FILE = 'https://www.mollie.com/.well-known/apple-developer-merchantid-domain-association';

    public function testDomainVerificationUrlRemainsUnchanged(): void
    {
        self::assertEquals(self::URL_FILE, ApplePayDomainVerificationService::URL_FILE);
    }
}
