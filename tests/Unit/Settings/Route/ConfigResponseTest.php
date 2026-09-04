<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings\Route;

use Mollie\Shopware\Component\Settings\Route\ConfigResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigResponse::class)]
final class ConfigResponseTest extends TestCase
{
    public function testStoreApiPayloadCarriesTheFieldNamesTheStorefrontReads(): void
    {
        $response = new ConfigResponse('pfl_123', true, 'de_DE', true, true);

        $this->assertSame(
            [
                'profileId' => 'pfl_123',
                'testMode' => true,
                'locale' => 'de_DE',
                'oneClickPayments' => true,
                'creditCardComponents' => true,
            ],
            $response->getObject()->all()
        );
    }

    public function testApiAliasIdentifiesTheConfigResponse(): void
    {
        $response = new ConfigResponse('pfl_123', true, 'de_DE', true, true);

        $this->assertSame('mollie_payments_config', $response->getObject()->getApiAlias());
    }

    public function testLiveModeWithoutOneClickPaymentsIsReportedAsSuch(): void
    {
        $response = new ConfigResponse('pfl_123', false, 'nl_NL', false, false);

        $this->assertFalse($response->getObject()->all()['testMode']);
        $this->assertFalse($response->getObject()->all()['oneClickPayments']);
        $this->assertFalse($response->getObject()->all()['creditCardComponents']);
    }
}
