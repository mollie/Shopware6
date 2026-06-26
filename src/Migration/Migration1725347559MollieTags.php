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
class Migration1725347559MollieTags extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1725347559;
    }

    public function update(Connection $connection): void
    {
        $this->createTag($connection, SubscriptionTag::ID, SubscriptionTag::NAME);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function createTag(Connection $connection, string $id, string $name): void
    {
        // migrations may run on every plugin install; tags are not purged so we must not duplicate them
        if ($this->tagExists($connection, $id)) {
            return;
        }

        $stmt = $connection->prepare(<<<'SQL'
            REPLACE INTO tag (id, name, created_at, updated_at)
            VALUES (:id, :name, :created_at, :updated_at)
        SQL);

        $stmt->bindValue(':id', Uuid::fromHexToBytes($id));
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':created_at', (new \DateTime())->format('Y-m-d H:i:s'));
        $stmt->bindValue(':updated_at', null);

        $stmt->executeStatement();
    }

    private function tagExists(Connection $connection, string $id): bool
    {
        $qb = $connection->createQueryBuilder();
        $qb->select('id')
            ->from('tag')
            ->where('id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($id))
        ;

        return $qb->executeQuery()->rowCount() > 0;
    }
}
