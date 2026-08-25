<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Settings\Struct\ExpressComponentsSettings;
use Mollie\Shopware\Component\Settings\Struct\PayPalExpressSettings;
use Mollie\Shopware\Component\Settings\Struct\RefundSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

#[CoversClass(SettingsService::class)]
final class SettingsServiceTest extends TestCase
{
    private const SALES_CHANNEL = 'sales-channel-1';

    private StaticSystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService();
    }

    public function testPluginConfigurationIsReadFromTheMollieDomain(): void
    {
        $this->systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . RefundSettings::KEY_ENABLED, true);
        $this->systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . RefundSettings::KEY_CREDIT_NOTES_PREFIX, 'CN-');

        $settings = $this->settingsService()->getRefundSettings();

        $this->assertTrue($settings->isEnabled());
        $this->assertSame('CN-', $settings->getCreditNotesPrefix());
    }

    public function testAccountSettingsComeFromTheShopwareCoreDomainNotFromThePluginDomain(): void
    {
        $this->systemConfigService->set('core.loginRegistration.showPhoneNumberField', true);
        $this->systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.loginRegistration.showPhoneNumberField', false);

        $settings = $this->settingsService()->getAccountSettings();

        $this->assertTrue($settings->isPhoneFieldShown());
    }

    public function testAShopWithoutAnyPluginConfigurationFallsBackToTheDefaults(): void
    {
        $settings = $this->settingsService()->getRefundSettings();

        $this->assertFalse($settings->isEnabled());
        $this->assertSame('', $settings->getCreditNotesPrefix());
    }

    public function testEachSalesChannelSeesItsOwnConfiguration(): void
    {
        $this->systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . RefundSettings::KEY_ENABLED, false);
        $this->systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . RefundSettings::KEY_ENABLED, true, self::SALES_CHANNEL);

        $settingsService = $this->settingsService();

        $this->assertFalse($settingsService->getRefundSettings()->isEnabled());
        $this->assertTrue($settingsService->getRefundSettings(self::SALES_CHANNEL)->isEnabled());
    }

    public function testConfigurationChangedAfterTheFirstReadIsOnlyPickedUpAfterClearingTheCache(): void
    {
        $this->systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . RefundSettings::KEY_ENABLED, false);
        $settingsService = $this->settingsService();
        $settingsService->getRefundSettings();

        $this->systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . RefundSettings::KEY_ENABLED, true);

        $this->assertFalse($settingsService->getRefundSettings()->isEnabled());

        $settingsService->clearCache();

        $this->assertTrue($settingsService->getRefundSettings()->isEnabled());
    }

    public function testStoredApiSettingsAreWrittenWithTheMollieConfigPrefix(): void
    {
        $settingsService = $this->settingsService();

        $settingsService->setApiSettings(ApiSettings::createFromShopwareArray([
            ApiSettings::KEY_TEST_API_KEY => 'test_key',
            ApiSettings::KEY_LIVE_API_KEY => 'live_key',
            ApiSettings::KEY_TEST_MODE => true,
            ApiSettings::KEY_PROFILE_ID => 'pfl_1',
        ]), self::SALES_CHANNEL);

        $this->assertSame(
            [
                'testApiKey' => 'test_key',
                'liveApiKey' => 'live_key',
                'testMode' => true,
                'profileId' => 'pfl_1',
            ],
            $this->systemConfigService->get(SettingsService::SYSTEM_CONFIG_DOMAIN, self::SALES_CHANNEL)
        );
    }

    public function testStoredApiSettingsAreReadableImmediatelyWithoutClearingTheCache(): void
    {
        $settingsService = $this->settingsService();

        $settingsService->setApiSettings(ApiSettings::createFromShopwareArray([
            ApiSettings::KEY_TEST_API_KEY => 'test_key',
            ApiSettings::KEY_TEST_MODE => true,
        ]));

        $this->assertSame('test_key', $settingsService->getApiSettings()->getApiKey());
    }

    public function testPaypalExpressStaysOffWhileTheBetaFlagIsNotSet(): void
    {
        $settings = $this->settingsService()->getPaypalExpressSettings();

        $this->assertFalse($settings->isEnabled());
    }

    public function testPaypalExpressIsEnabledByTheBetaFlag(): void
    {
        $settings = $this->settingsService(paypalExpressEnabled: '1')->getPaypalExpressSettings();

        $this->assertTrue($settings->isEnabled());
    }

    public function testPaypalExpressButtonFallsBackToTheEnvironmentWhenTheMerchantConfiguredNothing(): void
    {
        $settingsService = $this->settingsService(
            paypalExpressEnabled: '1',
            paypalExpressStyle: '3',
            paypalExpressShape: '2',
            paypalExpressRestrictions: 'pdp cart'
        );

        $settings = $settingsService->getPaypalExpressSettings();

        $this->assertSame(3, $settings->getStyle());
        $this->assertSame(2, $settings->getShape());
        $this->assertSame(['pdp', 'cart'], $settings->getRestrictions()->toArray());
    }

    public function testMerchantConfigurationBeatsThePaypalExpressEnvironmentFallback(): void
    {
        $this->systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . PayPalExpressSettings::KEY_BUTTON_STYLE, 5);
        $this->systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . PayPalExpressSettings::KEY_BUTTON_SHAPE, 4);
        $this->systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . PayPalExpressSettings::KEY_RESTRICTIONS, ['confirm']);

        $settingsService = $this->settingsService(
            paypalExpressEnabled: '1',
            paypalExpressStyle: '3',
            paypalExpressShape: '2',
            paypalExpressRestrictions: 'pdp cart'
        );

        $settings = $settingsService->getPaypalExpressSettings();

        $this->assertSame(5, $settings->getStyle());
        $this->assertSame(4, $settings->getShape());
        $this->assertSame(['confirm'], $settings->getRestrictions()->toArray());
    }

    public function testExpressComponentsStayOffWhileTheBetaFlagIsNotSet(): void
    {
        $settings = $this->settingsService()->getExpressComponentsSettings();

        $this->assertFalse($settings->isEnabled());
    }

    public function testExpressComponentsRestrictionsFallBackToTheEnvironment(): void
    {
        $settingsService = $this->settingsService(
            expressComponentsEnabled: '1',
            expressComponentsRestrictions: 'cart offcanvas'
        );

        $settings = $settingsService->getExpressComponentsSettings();

        $this->assertTrue($settings->isEnabled());
        $this->assertSame(['cart', 'offcanvas'], $settings->getRestrictions()->toArray());
    }

    public function testMerchantConfigurationBeatsTheExpressComponentsEnvironmentFallback(): void
    {
        $this->systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ExpressComponentsSettings::KEY_RESTRICTIONS, ['confirm']);

        $settings = $this->settingsService(expressComponentsEnabled: '1', expressComponentsRestrictions: 'cart')->getExpressComponentsSettings();

        $this->assertSame(['confirm'], $settings->getRestrictions()->toArray());
    }

    public function testDevAndCypressModeAreReportedFromTheEnvironment(): void
    {
        $settings = $this->settingsService(devMode: '1', cypressMode: '1')->getEnvironmentSettings();

        $this->assertTrue($settings->isDevMode());
        $this->assertTrue($settings->isCypressMode());
    }

    public function testDevAndCypressModeAreOffWithoutTheEnvironmentVariables(): void
    {
        $settings = $this->settingsService()->getEnvironmentSettings();

        $this->assertFalse($settings->isDevMode());
        $this->assertFalse($settings->isCypressMode());
    }

    public function testTheSettingsServiceCannotBeDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->settingsService()->getDecorated();
    }

    private function settingsService(
        ?string $devMode = null,
        ?string $cypressMode = null,
        ?string $paypalExpressEnabled = null,
        ?string $paypalExpressStyle = '1',
        ?string $paypalExpressShape = '1',
        ?string $paypalExpressRestrictions = '',
        ?string $expressComponentsEnabled = null,
        ?string $expressComponentsRestrictions = ''
    ): SettingsService {
        return new SettingsService(
            $this->systemConfigService,
            $devMode,
            $cypressMode,
            $paypalExpressEnabled,
            $paypalExpressStyle,
            $paypalExpressShape,
            $paypalExpressRestrictions,
            $expressComponentsEnabled,
            $expressComponentsRestrictions
        );
    }
}
