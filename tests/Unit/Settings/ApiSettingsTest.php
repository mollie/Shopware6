<?php
declare(strict_types=1);

namespace Mollie\Unit\Settings;

use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use PHPUnit\Framework\TestCase;

final class ApiSettingsTest extends TestCase
{
    public function testCanCreateApiSettingsFromArray(): void
    {
        $data = [
            ApiSettings::KEY_TEST_API_KEY => 'test_key',
            ApiSettings::KEY_LIVE_API_KEY => 'live_key',
            ApiSettings::KEY_TEST_MODE => true,
        ];
        $settings = ApiSettings::createFromShopwareArray($data);

        $this->assertSame('test_key', $settings->getTestApiKey());
        $this->assertSame('live_key', $settings->getLiveApiKey());
        $this->assertTrue($settings->isTestMode());
        $this->assertSame('test_key', $settings->getApiKey());
    }
}
