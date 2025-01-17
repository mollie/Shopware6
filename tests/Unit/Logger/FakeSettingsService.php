<?php
declare(strict_types=1);

namespace Mollie\Unit\Logger;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;

final class FakeSettingsService extends AbstractSettingsService
{
    private LoggerSettings $settings;

    public function __construct(LoggerSettings $settings = null)
    {
        if($settings === null) {
            $settings = new LoggerSettings(true,0);
        }
        $this->settings = $settings;
    }
    public function getDecorated(): AbstractSettingsService
    {
        // TODO: Implement getDecorated() method.
    }

    public function getLoggerSettings(?string $salesChannelId = null): LoggerSettings
    {
        return $this->settings;
    }
}