<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\Logger\Services;

use Kiener\MolliePayments\Service\Logger\Services\URLAnonymizer;
use PHPUnit\Framework\TestCase;

class URLAnonymizerTest extends TestCase
{
    /**
     * The payment finalize URL has a token in the query.
     * We need to make sure to remove this from the logs
     * @return void
     */
    public function testPaymentFinalizeIsAnonymized(): void
    {
        $anonymizer = new URLAnonymizer();

        $url = 'https://shop.phpunit.mollie/payment/finalize-transaction?token=abc';

        $this->assertEquals('https://shop.phpunit.mollie/payment/finalize-transaction', $anonymizer->anonymize($url));
    }
}
