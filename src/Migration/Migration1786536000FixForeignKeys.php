<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1786536000FixForeignKeys extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786536000;
    }

    public function update(Connection $connection): void
    {
        $utils = new MigrationUtils($connection);

        // the foreign keys were bound to the non-unique id index instead of the primary key, which MySQL 8.4 rejects
        $utils->dropConstraint('mollie_subscription_history', 'fk.mollie_subscription_history.mollie_subscription');
        $utils->dropConstraint('mollie_subscription_address', 'fk.mollie_subscription_history.mollie_subscription_address');

        $utils->dropIndex('mollie_subscription', 'idx.mollie_subscription.id');
        $utils->dropIndex('mollie_subscription_history', 'idx.mollie_subscription_history.id');
        $utils->dropIndex('mollie_subscription_address', 'idx.mollie_subscription_address.id');

        $utils->addConstraint(
            'mollie_subscription_history',
            'fk.mollie_subscription_history.mollie_subscription',
            'FOREIGN KEY (`subscription_id`)
                REFERENCES `mollie_subscription` (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
        );

        $utils->addConstraint(
            'mollie_subscription_address',
            'fk.mollie_subscription_address.mollie_subscription',
            'FOREIGN KEY (`subscription_id`)
                REFERENCES `mollie_subscription` (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
        );

        // foreign key names are unique per database, generic names collide with other plugins
        $utils->dropConstraint('mollie_refund_item', 'fk.refund_id');
        $utils->dropConstraint('mollie_refund_item', 'fk.order_line_item_id');

        $utils->addConstraint(
            'mollie_refund_item',
            'fk.mollie_refund_item.refund_id',
            'FOREIGN KEY (`refund_id`)
                REFERENCES `mollie_refund` (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
        );

        $utils->addConstraint(
            'mollie_refund_item',
            'fk.mollie_refund_item.order_line_item_id',
            'FOREIGN KEY (`order_line_item_id`, `order_line_item_version_id`)
                REFERENCES `order_line_item` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
