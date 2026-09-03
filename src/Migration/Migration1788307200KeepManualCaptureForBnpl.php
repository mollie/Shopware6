<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Settings\Struct\CaptureSettings;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Direct payment is the default for every method that supports it, which would move Klarna and Billie
 * from a capture on shipment to a payment collected at the checkout. A shop that updates keeps the
 * behaviour its merchant knows until they decide otherwise, so both methods are switched off once.
 *
 * Written only when the setting has never been saved, so an explicit choice by the merchant survives.
 *
 * @internal
 */
class Migration1788307200KeepManualCaptureForBnpl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788307200;
    }

    public function update(Connection $connection): void
    {
        $configKey = SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . CaptureSettings::KEY_DISABLED_METHODS;

        $exists = $connection->createQueryBuilder()
            ->select('id')
            ->from('system_config')
            ->where('configuration_key = :configurationKey')
            ->andWhere('sales_channel_id IS NULL')
            ->setParameter('configurationKey', $configKey)
            ->executeQuery()
            ->rowCount() > 0
        ;

        if ($exists) {
            return;
        }

        // Shopware wraps every system config value in this envelope, so a hand written row has to
        // carry it as well or SystemConfigService reads null.
        $configurationValue = (string) json_encode([
            '_value' => [
                PaymentMethod::KLARNA->value,
                PaymentMethod::BILLIE->value,
            ],
        ]);

        $sql = <<<'SQL'
            INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at)
            VALUES (:id, :configurationKey, :configurationValue, NULL, :createdAt)
        SQL;

        $stmt = $connection->prepare($sql);

        $stmt->bindValue(':id', Uuid::randomBytes());
        $stmt->bindValue(':configurationKey', $configKey);
        $stmt->bindValue(':configurationValue', $configurationValue);
        $stmt->bindValue(':createdAt', (new \DateTime())->format('Y-m-d H:i:s'));

        $stmt->executeStatement();
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
