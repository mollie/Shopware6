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
class Migration1779321600FixMissingVippsMobilePayMethods extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779321600;
    }

    public function update(Connection $connection): void
    {
        $replacementClasses = [
            'Kiener\MolliePayments\Handler\Method\VippsPayment' => \Mollie\Shopware\Component\Payment\Method\VippsPayment::class,
            'Kiener\MolliePayments\Handler\Method\MobilePayPayment' => \Mollie\Shopware\Component\Payment\Method\MobilePayPayment::class,
        ];

        foreach ($replacementClasses as $class => $replacementClass) {
            $replacementClass = addslashes($replacementClass);
            $class = addslashes($class);
            $sql = <<<SQL
UPDATE payment_method SET handler_identifier = '{$replacementClass}' WHERE handler_identifier = '{$class}';
SQL;

            $connection->executeStatement($sql);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
