<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;
use PHPUnit\Framework\TestCase;

final class LoggerSettingsTest extends TestCase
{
    public function testCanCreateApiSettingsFromArray(): void
    {
        $data = [
            LoggerSettings::KEY_DEBUG_MODE => false,
            LoggerSettings::KEY_LOG_FILE_DAYS => 5,
        ];
        $settings = LoggerSettings::createFromShopwareArray($data);

        $this->assertFalse($settings->isDebugMode());
        $this->assertSame(5, $settings->getLogFileDays());
    }
}
