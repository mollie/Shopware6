<?php
declare(strict_types=1);

namespace Mollie\Unit\Logger;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Settings\Struct\EnvironmentSettings;
use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;

final class FakeSettingsService extends AbstractSettingsService
{

    public function __construct(private ?LoggerSettings $loggerSettings = null, private ?PaymentSettings $paymentSettings = null)
    {
        if($this->loggerSettings === null) {
            $this->loggerSettings = new LoggerSettings(true,0);
        }
        if($this->paymentSettings === null) {
            $this->paymentSettings = new PaymentSettings();
        }

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
        return $this->paymentSettings;
    }


    public function getLoggerSettings(?string $salesChannelId = null): LoggerSettings
    {
        return $this->loggerSettings;
    }
}