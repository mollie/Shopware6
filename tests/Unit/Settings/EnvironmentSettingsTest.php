<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Settings\Struct\EnvironmentSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnvironmentSettings::class)]
final class EnvironmentSettingsTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $settings = new EnvironmentSettings(true, false);
        $this->assertTrue($settings->isDevMode());
        $this->assertFalse($settings->isCypressMode());
    }
}
