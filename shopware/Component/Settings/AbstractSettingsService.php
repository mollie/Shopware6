<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings;

use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;
use Mollie\shopware\Component\Settings\Struct\PaymentSettings;

abstract class AbstractSettingsService
{
    abstract public function getDecorated(): AbstractSettingsService;

    abstract public function getLoggerSettings(?string $salesChannelId = null): LoggerSettings;

    abstract public function getApiSettings(?string $salesChannelId = null): ApiSettings;

    abstract public function getPaymentSettings(?string $salesChannelId = null): PaymentSettings;
}
