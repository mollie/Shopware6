<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Settings;

use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestriction;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Integration\Data\ShopwareTestBehaviour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(SettingsService::class)]
#[Group('core')]
final class ExpressComponentsSettingsTest extends TestCase
{
    use ShopwareTestBehaviour;
    use IntegrationTestBehaviour;

    public function testExpressComponentsAreDisabledByDefault(): void
    {
        /**
         * @var SystemConfigService $systemConfigService
         */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $settingsService = new SettingsService($systemConfigService);

        $settings = $settingsService->getExpressComponentsSettings();

        $this->assertFalse($settings->isEnabled());
        $this->assertCount(0, $settings->getRestrictions());
    }

    public function testExpressComponentsAreLoadedFromEnvironment(): void
    {
        /**
         * @var SystemConfigService $systemConfigService
         */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $settingsService = new SettingsService(
            $systemConfigService,
            expressComponentsEnabled: '1',
            expressComponentsRestrictions: 'pdp cart'
        );

        $settings = $settingsService->getExpressComponentsSettings();

        $this->assertTrue($settings->isEnabled());
        $this->assertCount(2, $settings->getRestrictions());
        $this->assertSame(
            [VisibilityRestriction::PRODUCT_DETAIL_PAGE->value, VisibilityRestriction::CART->value],
            $settings->getRestrictions()->toArray()
        );
    }

    public function testUnknownRestrictionsAreIgnored(): void
    {
        /**
         * @var SystemConfigService $systemConfigService
         */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $settingsService = new SettingsService(
            $systemConfigService,
            expressComponentsEnabled: '1',
            expressComponentsRestrictions: 'pdp foo'
        );

        $settings = $settingsService->getExpressComponentsSettings();

        $this->assertSame([VisibilityRestriction::PRODUCT_DETAIL_PAGE->value], $settings->getRestrictions()->toArray());
    }
}
