<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings;

use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;

abstract class AbstractSettingsService
{
    abstract public function getDecorated(): AbstractSettingsService;
    abstract public function getLoggerSettings(?string $salesChannelId = null): LoggerSettings;
}
