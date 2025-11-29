<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Settings;

use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class PaymentSettingsTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSettingsAreLoadedFromDatabase(): void
    {
        /**
         * @var SystemConfigService $systemConfigService
         */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $oldNumberFormat = $systemConfigService->get(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . PaymentSettings::KEY_ORDER_NUMBER_FORMAT);
        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . PaymentSettings::KEY_ORDER_NUMBER_FORMAT, 'test_{ordernumber}_{customernumber}');
        $devMode = (bool) EnvironmentHelper::getVariable('MOLLIE_DEV_MODE', false);
        $cypressMode = (bool) EnvironmentHelper::getVariable('MOLLIE_CYPRESS_MODE', false);
        $settingsService = new SettingsService($this->getContainer(), $devMode, $cypressMode);
        $paymentSettings = $settingsService->getPaymentSettings();
        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . PaymentSettings::KEY_ORDER_NUMBER_FORMAT, $oldNumberFormat);
        $this->assertSame('test_{ordernumber}_{customernumber}', $paymentSettings->getOrderNumberFormat());
    }

    public function testSettingsAreCachedPerSalesChannel(): void
    {
        $settingsService = $this->getContainer()->get(SettingsService::class);

        $expectedSettings = $settingsService->getPaymentSettings();
        $actualSettings = $settingsService->getPaymentSettings();
        $differentSalesChannelSettings = $settingsService->getPaymentSettings(Uuid::randomHex());

        $this->assertSame($expectedSettings, $actualSettings);
        $this->assertNotSame($actualSettings, $differentSalesChannelSettings);
    }
}
