<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1768084646SubscriptionOrderVersionId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1768084646;
    }

    public function update(Connection $connection): void
    {
        $utils = new MigrationUtils($connection);

        $utils->createColumn('mollie_subscription', 'order_version_id', 'BINARY(16)', '', 'order_id');
        $utils->dropIndex('mollie_subscription', 'idx.mollie_subscription.product_id');
        $utils->deleteColumn('mollie_subscription', 'quantity');
        $utils->deleteColumn('mollie_subscription', 'product_id');

        $utils->addConstraint(
            'mollie_subscription',
            'fk.mollie_subscription.order',
            'FOREIGN KEY (`order_id`, `order_version_id`)
                REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE'
        );

        $utils->addConstraint(
            'mollie_subscription_history',
            'fk.mollie_subscription_history.mollie_subscription',
            'FOREIGN KEY (`subscription_id`)
                REFERENCES `mollie_subscription` (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
        );

        $utils->addConstraint(
            'mollie_subscription_address',
            'fk.mollie_subscription_history.mollie_subscription_address',
            'FOREIGN KEY (`subscription_id`)
                REFERENCES `mollie_subscription` (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
