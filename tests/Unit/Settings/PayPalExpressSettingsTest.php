<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Settings\Struct\PayPalExpressSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PayPalExpressSettings::class)]
final class PayPalExpressSettingsTest extends TestCase
{
    public function testEnabledGetters(): void
    {
        $settings = new PayPalExpressSettings(true);

        $this->assertTrue($settings->getEnabled());
        $this->assertTrue($settings->isEnabled());
    }

    public function testDisabledGetters(): void
    {
        $settings = new PayPalExpressSettings(false);

        $this->assertFalse($settings->getEnabled());
        $this->assertFalse($settings->isEnabled());
    }

    public function testDefaultShapeAndStyle(): void
    {
        $settings = new PayPalExpressSettings(true);

        $this->assertSame(0, $settings->getShape());
        $this->assertSame(0, $settings->getStyle());
        $this->assertSame([], $settings->getRestrictions());
    }

    public function testSetAndGetShape(): void
    {
        $settings = new PayPalExpressSettings(true);
        $settings->setShape(2);

        $this->assertSame(2, $settings->getShape());
    }

    public function testSetAndGetStyle(): void
    {
        $settings = new PayPalExpressSettings(true);
        $settings->setStyle(3);

        $this->assertSame(3, $settings->getStyle());
    }

    public function testSetAndGetRestrictions(): void
    {
        $settings = new PayPalExpressSettings(true);
        $settings->setRestrictions(['product', 'cart']);

        $this->assertSame(['product', 'cart'], $settings->getRestrictions());
    }
}
