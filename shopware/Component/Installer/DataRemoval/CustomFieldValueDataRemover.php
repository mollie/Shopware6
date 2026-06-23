<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Installer\DataRemoval;

use Doctrine\DBAL\Connection;
use Mollie\Shopware\Mollie;
use Shopware\Core\Framework\Context;

/**
 * Strips the Mollie data out of the custom_fields JSON of every entity that stores it. All of
 * customer, order, order line item, order transaction and order delivery keep their Mollie data
 * under the single {@see Mollie::EXTENSION} key, so the whole key is removed in one go. Product
 * custom fields are stored as individual flat keys (no wrapping array), so those are removed by
 * key.
 */
final class CustomFieldValueDataRemover implements DataRemoverInterface
{
    /**
     * Core tables that keep their Mollie data under the Mollie::EXTENSION key in custom_fields.
     */
    private const EXTENSION_TABLES = [
        'customer',
        'order',
        'order_line_item',
        'order_transaction',
        'order_delivery',
    ];

    /**
     * Product custom fields are stored as flat keys, not under the Mollie::EXTENSION array.
     */
    private const PRODUCT_CUSTOM_FIELD_KEYS = [
        'mollie_payments_product_voucher_type',
        'mollie_payments_product_subscription_enabled',
        'mollie_payments_product_subscription_interval',
        'mollie_payments_product_subscription_interval_unit',
        'mollie_payments_product_subscription_repetition',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function remove(Context $context): void
    {
        $extensionPath = '$.' . Mollie::EXTENSION;
        foreach (self::EXTENSION_TABLES as $table) {
            $this->removeJsonKey($table, $extensionPath);
        }

        foreach (self::PRODUCT_CUSTOM_FIELD_KEYS as $key) {
            $this->removeJsonKey('product_translation', '$.' . $key);
        }
    }

    private function removeJsonKey(string $table, string $jsonPath): void
    {
        // The table name comes from a fixed whitelist above and the JSON path from constants,
        // so there is no user input in this statement; the path is still bound as a parameter.
        $this->connection->executeStatement(
            'UPDATE `' . $table . '` SET custom_fields = JSON_REMOVE(custom_fields, :path)
             WHERE custom_fields IS NOT NULL AND JSON_CONTAINS_PATH(custom_fields, \'one\', :path)',
            ['path' => $jsonPath]
        );
    }
}
