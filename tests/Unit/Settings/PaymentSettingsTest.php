<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentSettings::class)]
final class PaymentSettingsTest extends TestCase
{
    public function testCanCreateApiSettingsFromArray(): void
    {
        $data = [
            PaymentSettings::KEY_ORDER_NUMBER_FORMAT => 'test-{orderNumber}'
        ];
        $settings = PaymentSettings::createFromShopwareArray($data);

        $this->assertSame('test-{orderNumber}', $settings->getOrderNumberFormat());
    }

    public function testRoundingDiffDefaults(): void
    {
        $settings = PaymentSettings::createFromShopwareArray([]);

        $this->assertFalse($settings->isFixRoundingDiffEnabled());
        $this->assertSame('', $settings->getFixRoundingDiffName());
        $this->assertSame('', $settings->getFixRoundingDiffSku());
    }

    public function testRoundingDiffFromArray(): void
    {
        $data = [
            PaymentSettings::KEY_FIX_ROUNDING_DIFF_ENABLED => true,
            PaymentSettings::KEY_FIX_ROUNDING_DIFF_NAME => 'Rounding',
            PaymentSettings::KEY_FIX_ROUNDING_DIFF_SKU => 'ROUND-1',
        ];
        $settings = PaymentSettings::createFromShopwareArray($data);

        $this->assertTrue($settings->isFixRoundingDiffEnabled());
        $this->assertSame('Rounding', $settings->getFixRoundingDiffName());
        $this->assertSame('ROUND-1', $settings->getFixRoundingDiffSku());
    }
}
