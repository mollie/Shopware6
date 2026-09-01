<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestriction;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestrictionCollection;
use Mollie\Shopware\Component\Settings\Struct\ExpressComponentsSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpressComponentsSettings::class)]
final class ExpressComponentsSettingsTest extends TestCase
{
    public function testDisabledWithoutRestrictions(): void
    {
        $settings = new ExpressComponentsSettings(false);

        $this->assertFalse($settings->isEnabled());
        $this->assertFalse($settings->getEnabled());
        $this->assertCount(0, $settings->getRestrictions());
    }

    public function testRestrictionsAreKept(): void
    {
        $settings = new ExpressComponentsSettings(true);
        $settings->setRestrictions(VisibilityRestrictionCollection::fromArray(['cart', 'confirm', 'unknown']));

        $this->assertTrue($settings->isEnabled());
        $this->assertSame(
            [VisibilityRestriction::CART->value, VisibilityRestriction::CONFIRM->value],
            $settings->getRestrictions()->toArray()
        );
    }
}
