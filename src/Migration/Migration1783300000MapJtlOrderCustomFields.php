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
class Migration1783300000MapJtlOrderCustomFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1783300000;
    }

    public function update(Connection $connection): void
    {
        /**
         * The JTL connector reads the fixed keys order_id, payment_id and third_party_payment_id
         * from the order custom_fields->mollie_payments object. The payment data is stored with the
         * camelCase keys orderId, id and thirdPartyPaymentId, so JTL never finds the values.
         * Fill the fixed keys from the camelCase values for every order that has the mollie_payments
         * object, but keep any existing non-empty snake_case value: pre-5.0 orders only carry the
         * snake_case keys and would otherwise be overwritten with an empty string.
         */
        $sql = <<<'SQL'
            UPDATE `order`
            SET `custom_fields` = JSON_SET(
                `custom_fields`,
                '$.mollie_payments.order_id',
                COALESCE(
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, '$.mollie_payments.order_id')), ''),
                    JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, '$.mollie_payments.orderId')),
                    ''
                ),
                '$.mollie_payments.payment_id',
                COALESCE(
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, '$.mollie_payments.payment_id')), ''),
                    JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, '$.mollie_payments.id')),
                    ''
                ),
                '$.mollie_payments.third_party_payment_id',
                COALESCE(
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, '$.mollie_payments.third_party_payment_id')), ''),
                    JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, '$.mollie_payments.thirdPartyPaymentId')),
                    ''
                )
            )
            WHERE JSON_EXTRACT(`custom_fields`, '$.mollie_payments') IS NOT NULL
        SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do
    }
}
