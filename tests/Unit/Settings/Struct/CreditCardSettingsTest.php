<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings\Struct;

use Mollie\Shopware\Component\Settings\Struct\CreditCardSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreditCardSettings::class)]
final class CreditCardSettingsTest extends TestCase
{
    public function testComponentsAreEnabledWhenTheMerchantSwitchedThemOn(): void
    {
        $settings = CreditCardSettings::createFromShopwareArray(['enableCreditCardComponents' => true]);

        $this->assertTrue($settings->isCreditCardComponentsEnabled());
    }

    public function testComponentsAreOffForAShopThatNeverConfiguredThem(): void
    {
        $settings = CreditCardSettings::createFromShopwareArray([]);

        $this->assertFalse($settings->isCreditCardComponentsEnabled());
    }

    public function testConfigurationValueIsCastToBool(): void
    {
        $settings = CreditCardSettings::createFromShopwareArray(['enableCreditCardComponents' => '1']);

        $this->assertTrue($settings->isCreditCardComponentsEnabled());
    }
}
