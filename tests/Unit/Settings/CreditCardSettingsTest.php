<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Settings\Struct\CreditCardSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreditCardSettings::class)]
final class CreditCardSettingsTest extends TestCase
{
    public function testDefaultIsDisabled(): void
    {
        $settings = new CreditCardSettings();

        $this->assertFalse($settings->isCreditCardComponentsEnabled());
    }

    public function testEnabledWhenTrue(): void
    {
        $settings = new CreditCardSettings(true);

        $this->assertTrue($settings->isCreditCardComponentsEnabled());
    }

    public function testCreateFromShopwareArrayEnabled(): void
    {
        $settings = CreditCardSettings::createFromShopwareArray(['enableCreditCardComponents' => true]);

        $this->assertTrue($settings->isCreditCardComponentsEnabled());
    }

    public function testCreateFromShopwareArrayDisabledByDefault(): void
    {
        $settings = CreditCardSettings::createFromShopwareArray([]);

        $this->assertFalse($settings->isCreditCardComponentsEnabled());
    }
}
