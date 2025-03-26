<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Kiener\MolliePayments\Struct\Tags\AbstractTag;
use Kiener\MolliePayments\Struct\Tags\SubscriptionTag;
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
        $tags = [SubscriptionTag::create()];

        foreach ($tags as $tag) {
            /* @var $tag AbstractTag */
            $this->createTag($connection, $tag->getId(), $tag->getName());
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createTag(
        Connection $connection,
        string $id,
        string $name
    ): void {
        // migration must be able to run multiple times in succession
        // with every install of the mollie plugin all migrations are run
        // since tags don't count as plugin data, they're not purged and they must not be created again
        if ($this->tagExists($connection, $id)) {
            return;
        }

        $query = <<<'SQL'
        REPLACE INTO tag 
        (id, name, created_at, updated_at) 
        VALUES (:id, :name, :created_at, :updated_at)
        SQL;

        $stmt = $connection->prepare($query);

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

        $result = $qb->executeStatement();

        if ($result instanceof Result) {
            return $result->rowCount() > 0;
        }

        return false;
    }
}
