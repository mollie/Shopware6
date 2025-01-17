<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

interface PluginSettingsServiceInterface
{
    /**
     * @return string
     */
    public function getEnvMollieShopDomain(): string;

    /**
     * @return bool
     */
    public function getEnvMollieDevMode(): bool;
}
