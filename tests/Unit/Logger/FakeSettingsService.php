<?php
declare(strict_types=1);

namespace Mollie\Unit\Logger;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Settings\Struct\EnvironmentSettings;
use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;
use Mollie\shopware\Component\Settings\Struct\PaymentSettings;

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

    public function getEnvironmentSettings(): EnvironmentSettings
    {
        // TODO: Implement getEnvironmentSettings() method.
    }

    public function getDecorated(): AbstractSettingsService
    {
        // TODO: Implement getDecorated() method.
    }

    public function getApiSettings(?string $salesChannelId = null): ApiSettings
    {
        // TODO: Implement getApiSettings() method.
    }

    public function getPaymentSettings(?string $salesChannelId = null): PaymentSettings
    {
        // TODO: Implement getPaymentSettings() method.
    }


    public function getLoggerSettings(?string $salesChannelId = null): LoggerSettings
    {
        return $this->settings;
    }
}