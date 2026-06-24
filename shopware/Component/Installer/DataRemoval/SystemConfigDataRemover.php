<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Installer\DataRemoval;

use Doctrine\DBAL\Connection;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Core\Framework\Context;

/**
 * Removes every plugin configuration entry from system_config (all keys under the
 * {@see SettingsService::SYSTEM_CONFIG_DOMAIN} domain).
 */
final class SystemConfigDataRemover implements DataRemoverInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function remove(Context $context): void
    {
        $this->connection->executeStatement(
            'DELETE FROM system_config WHERE configuration_key LIKE :prefix',
            ['prefix' => SettingsService::SYSTEM_CONFIG_DOMAIN . '.%']
        );
    }
}
