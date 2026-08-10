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
class Migration1786363200BackfillSubscriptionShippingAddress extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786363200;
    }

    /**
     * Older plugin versions only stored a subscription shipping address when it differed from the
     * billing address, so shipping_address_id stayed NULL whenever both addresses were identical.
     * The renewal now requires both addresses, therefore the NULL side is pointed at the address row
     * that already exists, which is exactly what current subscriptions do when both addresses match.
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `mollie_subscription`
             SET shipping_address_id = billing_address_id
             WHERE shipping_address_id IS NULL
               AND billing_address_id IS NOT NULL'
        );

        $connection->executeStatement(
            'UPDATE `mollie_subscription`
             SET billing_address_id = shipping_address_id
             WHERE billing_address_id IS NULL
               AND shipping_address_id IS NOT NULL'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
