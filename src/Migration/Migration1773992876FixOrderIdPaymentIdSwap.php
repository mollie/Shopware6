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
class Migration1773992876FixOrderIdPaymentIdSwap extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773992876;
    }

    public function update(Connection $connection): void
    {
        /**
         * Fix a bug where Mollie payment IDs (starting with "tr_") were stored
         * in the order_id field instead of the payment_id field within the
         * custom_fields->mollie_payments JSON structure.
         *
         * Before: { "mollie_payments": { "order_id": "tr_xxx", "payment_id": "" } }
         * After:  { "mollie_payments": { "order_id": "", "payment_id": "tr_xxx" } }
         *
         * Only runs when payment_id is not already set, to avoid overwriting valid data.
         */
        $sql = <<<'SQL'
            UPDATE `order`
            SET `custom_fields` = JSON_SET(
                JSON_SET(
                    `custom_fields`,
                    '$.mollie_payments.payment_id',
                    JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, '$.mollie_payments.order_id'))
                ),
                '$.mollie_payments.order_id',
                ''
            )
            WHERE JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, '$.mollie_payments.order_id')) LIKE 'tr\_%'
              AND (
                  JSON_EXTRACT(`custom_fields`, '$.mollie_payments.payment_id') IS NULL
                  OR JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, '$.mollie_payments.payment_id')) = ''
              )
        SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do
    }
}
