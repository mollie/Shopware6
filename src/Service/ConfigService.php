<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigService
{
    public const SYSTEM_CONFIG_DOMAIN = 'MolliePayments.config.';

    /** @var SystemConfigService $systemConfigService */
    private $systemConfigService;

    /** @var string */
    private $salesChannelId;

    /**
     * Creates a new instance of the config service.
     *
     * @param SystemConfigService $systemConfigService
     * @param string|null         $salesChannelId
     */
    public function __construct(
        SystemConfigService $systemConfigService,
        ?string $salesChannelId = null
    )
    {
        $this->systemConfigService = $systemConfigService;
        $this->salesChannelId = $salesChannelId;
    }

    /**
     * Returns the system config service.
     *
     * @return SystemConfigService
     */
    public function getSystemConfigService(): SystemConfigService
    {
        return $this->systemConfigService;
    }

    /**
     * Gets a variable from the system config service.
     *
     * @param string      $name
     * @param string|null $salesChannelId
     *
     * @return array|mixed|null
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
     *
     * @param string      $name
     * @param string      $value
     * @param string|null $salesChannelId
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
     *
     * @param string      $name
     * @param string|null $salesChannelId
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
     *
     * @param string $salesChannelId
     */
    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }
}