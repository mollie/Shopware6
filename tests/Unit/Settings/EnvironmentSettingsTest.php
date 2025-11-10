<?php
declare(strict_types=1);

namespace Mollie\Unit\Settings;

use Mollie\Shopware\Component\Settings\Struct\EnvironmentSettings;
use PHPUnit\Framework\TestCase;

final class EnvironmentSettingsTest extends TestCase
{
    public function testSettersAndGetters(): void
    {
        $settings = new EnvironmentSettings(true, false);
        $this->assertTrue($settings->isDevMode());
        $this->assertFalse($settings->isCypressMode());
    }
}
