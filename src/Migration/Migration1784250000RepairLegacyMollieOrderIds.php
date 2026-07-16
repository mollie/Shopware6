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
class Migration1784250000RepairLegacyMollieOrderIds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784250000;
    }

    /**
     * Migration1783300000MapJtlOrderCustomFields overwrote the order custom_fields keys order_id/payment_id
     * with COALESCE(<camelCase>, ''). Orders created before 5.0 only carry the snake_case keys, never the
     * camelCase ones, so their valid ids were replaced with empty strings. The order_transaction was not
     * touched by that migration and still holds the ids, so restore them from there for every order whose
     * order_id and payment_id are now both empty. Snake_case (pre-5.0) wins over camelCase (5.x).
     */
    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
            UPDATE `order` `o`
            INNER JOIN `order_transaction` `ot`
                ON `ot`.`order_id` = `o`.`id`
                AND `ot`.`order_version_id` = `o`.`version_id`
            SET `o`.`custom_fields` = JSON_MERGE_PATCH(
                COALESCE(`o`.`custom_fields`, '{}'),
                JSON_OBJECT('mollie_payments', JSON_OBJECT(
                    'order_id',
                        COALESCE(
                            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`o`.`custom_fields`,  '$.mollie_payments.order_id')), ''),
                            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`ot`.`custom_fields`, '$.mollie_payments.order_id')), ''),
                            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`ot`.`custom_fields`, '$.mollie_payments.orderId')),  ''),
                            ''),
                    'payment_id',
                        COALESCE(
                            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`o`.`custom_fields`,  '$.mollie_payments.payment_id')), ''),
                            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`ot`.`custom_fields`, '$.mollie_payments.payment_id')), ''),
                            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`ot`.`custom_fields`, '$.mollie_payments.id')),         ''),
                            ''),
                    'third_party_payment_id',
                        COALESCE(
                            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`o`.`custom_fields`,  '$.mollie_payments.third_party_payment_id')), ''),
                            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`ot`.`custom_fields`, '$.mollie_payments.third_party_payment_id')), ''),
                            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`ot`.`custom_fields`, '$.mollie_payments.thirdPartyPaymentId')),     ''),
                            '')
                ))
            )
            WHERE JSON_EXTRACT(`ot`.`custom_fields`, '$.mollie_payments') IS NOT NULL
              AND COALESCE(
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`o`.`custom_fields`, '$.mollie_payments.order_id')),   ''),
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`o`.`custom_fields`, '$.mollie_payments.payment_id')), '')
                  ) IS NULL
        SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do
    }
}
