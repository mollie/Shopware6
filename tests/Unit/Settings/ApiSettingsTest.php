<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiSettings::class)]
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

    /**
     * The live key is what charges real money, so the switch between the two has to follow the
     * test mode flag exactly.
     */
    public function testTheLiveKeyIsUsedOutsideTestMode(): void
    {
        $settings = ApiSettings::createFromShopwareArray([
            ApiSettings::KEY_TEST_API_KEY => 'test_key',
            ApiSettings::KEY_LIVE_API_KEY => 'live_key',
            ApiSettings::KEY_TEST_MODE => false,
        ]);

        $this->assertFalse($settings->isTestMode());
        $this->assertSame(Mode::LIVE, $settings->getMode());
        $this->assertSame('live_key', $settings->getApiKey());
    }

    /**
     * A shop that never saved the plugin configuration has no test mode value. Defaulting to the
     * live key there would charge real money on an unconfigured shop.
     */
    public function testAnUnconfiguredShopStaysInTestMode(): void
    {
        $settings = ApiSettings::createFromShopwareArray([]);

        $this->assertTrue($settings->isTestMode());
        $this->assertSame('', $settings->getApiKey());
    }

    public function testTheProfileIsReadFromTheConfiguration(): void
    {
        $settings = ApiSettings::createFromShopwareArray([ApiSettings::KEY_PROFILE_ID => 'pfl_1']);

        $this->assertSame('pfl_1', $settings->getProfileId());
    }

    /**
     * The profile id is not entered by the merchant, it is looked up at Mollie and written back.
     */
    public function testTheProfileLookedUpAtMollieCanBeStored(): void
    {
        $settings = ApiSettings::createFromShopwareArray([]);

        $settings->setProfileId('pfl_1');

        $this->assertSame('pfl_1', $settings->getProfileId());
    }

    public function testTheSettingsAreWrittenBackUnderTheSameConfigurationKeys(): void
    {
        $settings = new ApiSettings('test_key', 'live_key', Mode::TEST, 'pfl_1');

        $this->assertSame([
            ApiSettings::KEY_TEST_API_KEY => 'test_key',
            ApiSettings::KEY_LIVE_API_KEY => 'live_key',
            ApiSettings::KEY_TEST_MODE => true,
            ApiSettings::KEY_PROFILE_ID => 'pfl_1',
        ], $settings->toShopwareArray());
    }
}
