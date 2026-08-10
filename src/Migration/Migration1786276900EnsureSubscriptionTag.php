<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Mollie\Shopware\Component\Subscription\SubscriptionTag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1786276900EnsureSubscriptionTag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786276900;
    }

    public function update(Connection $connection): void
    {
        $id = Uuid::fromHexToBytes(SubscriptionTag::ID);

        $exists = $connection->createQueryBuilder()
            ->select('id')
            ->from('tag')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->executeQuery()
            ->rowCount() > 0
        ;

        if ($exists) {
            return;
        }

        $stmt = $connection->prepare(<<<'SQL'
            REPLACE INTO tag (id, name, created_at, updated_at)
            VALUES (:id, :name, :created_at, :updated_at)
        SQL);

        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':name', SubscriptionTag::NAME);
        $stmt->bindValue(':created_at', (new \DateTime())->format('Y-m-d H:i:s'));
        $stmt->bindValue(':updated_at', null);

        $stmt->executeStatement();
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
