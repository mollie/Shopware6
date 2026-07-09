<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1783200000CopyPaymentExtensionToOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1783200000;
    }

    public function update(Connection $connection): void
    {
        /**
         * Until 5.0 the Mollie payment data (custom_fields->mollie_payments) was only stored on the
         * order_transaction. ERP systems such as the JTL connector only read the order, so those orders
         * never received the data. Backfill the mollie_payments object from the transaction onto the
         * order for every order that does not have it yet.
         */
        $sql = <<<'SQL'
            UPDATE `order` `o`
            INNER JOIN `order_transaction` `ot`
                ON `ot`.`order_id` = `o`.`id`
                AND `ot`.`order_version_id` = `o`.`version_id`
            SET `o`.`custom_fields` = JSON_SET(
                COALESCE(`o`.`custom_fields`, '{}'),
                '$.mollie_payments',
                JSON_EXTRACT(`ot`.`custom_fields`, '$.mollie_payments')
            )
            WHERE JSON_EXTRACT(`ot`.`custom_fields`, '$.mollie_payments') IS NOT NULL
              AND (
                  `o`.`custom_fields` IS NULL
                  OR JSON_EXTRACT(`o`.`custom_fields`, '$.mollie_payments') IS NULL
              )
        SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do
    }
}
