<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

interface PluginSettingsServiceInterface
{
    public function getEnvMollieShopDomain(): string;

    public function getEnvMollieDevMode(): bool;
}
