<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Service\ApplePayDirect;

use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayDomainVerificationService;
use PHPUnit\Framework\TestCase;

class ApplePayDomainVerificationServiceTest extends TestCase
{

    /**
     * This test verifies that our download URL of the official Mollie domain verification file
     * is not accidentally changed without recognizing it.
     * This is 1 global file for all merchatns.
     */
    public function testDownloadURL(): void
    {
        self::assertEquals(
            'https://www.mollie.com/.well-known/apple-developer-merchantid-domain-association',
            ApplePayDomainVerificationService::URL_FILE
        );
    }

    /**
     * This test verifies the local path of the downloaded domain verification file.
     * This must not be changed and always has to be in the .well-known folder of the public DocRoot.
     */
    public function testLocalFile(): void
    {
        self::assertEquals(
            '/.well-known/apple-developer-merchantid-domain-association',
            ApplePayDomainVerificationService::LOCAL_FILE
        );
    }

}
