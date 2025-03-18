<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigService
{
    public const SYSTEM_CONFIG_DOMAIN = 'MolliePayments.config.';

    public const ENABLE_SUBSCRIPTION = 'enableSubscriptions';
    public const PRE_PAYMENT_REMINDER_EMAIL = 'prePaymentReminderEmail';
    public const EMAIL_TEMPLATE = 'emailTemplate';
    public const DAYS_BEFORE_REMINDER = 'daysBeforeReminder';

    /** @var SystemConfigService */
    private $systemConfigService;

    /** @var null|string */
    private $salesChannelId;

    /**
     * @var MollieGatewayInterface
     */
    private $gatewayMollie;

    /**
     * @var SettingsService
     */
    private $settingsService;

    public function __construct(SystemConfigService $systemConfigService, MollieGatewayInterface $mollieGateway, SettingsService $settingsService, ?string $salesChannelId = null)
    {
        $this->systemConfigService = $systemConfigService;
        $this->salesChannelId = $salesChannelId;
        $this->settingsService = $settingsService;
        $this->gatewayMollie = $mollieGateway;
    }

    /**
     * Returns the system config service.
     */
    public function getSystemConfigService(): SystemConfigService
    {
        return $this->systemConfigService;
    }

    /**
     * Gets a variable from the system config service.
     *
     * @return null|array|mixed
     */
    public function get(string $name, ?string $salesChannelId = null)
    {
        return $this->getSystemConfigService()->get(
            self::SYSTEM_CONFIG_DOMAIN . $name,
            $salesChannelId ?? $this->salesChannelId
        );
    }

    /**
     * Sets a variable in the system config service.
     */
    public function set(string $name, string $value, ?string $salesChannelId = null): void
    {
        $this->getSystemConfigService()->set(
            self::SYSTEM_CONFIG_DOMAIN . $name,
            $value,
            $salesChannelId ?? $this->salesChannelId
        );
    }

    /**
     * Deletes a variable in the system config service.
     */
    public function delete(string $name, ?string $salesChannelId = null): void
    {
        $this->getSystemConfigService()->delete(
            self::SYSTEM_CONFIG_DOMAIN . $name,
            $salesChannelId ?? $this->salesChannelId
        );
    }

    /**
     * Sets the sales channel to get the configuration for.
     */
    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function fetchProfileId(string $salesChannelId): void
    {
        $this->gatewayMollie->switchClient($salesChannelId);

        $profileId = $this->gatewayMollie->getProfileId();

        $isTestMode = $this->settingsService->getSettings($salesChannelId)->isTestMode();

        $this->settingsService->setProfileId($profileId, $salesChannelId, $isTestMode);
    }
}
