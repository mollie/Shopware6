<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Migration;

use Doctrine\DBAL\Connection;
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
            /** @var $tag AbstractTag */
            $this->createTag($connection, $tag->getId(), $tag->getName());
        }
    }

    /**
     * @param Connection $connection
     * @return void
     */
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createTag(
        Connection $connection,
        string $id,
        string $name
    ): void {
        $query = <<<SQL
        INSERT INTO tag 
        (id, name, created_at, updated_at) 
        VALUES (:id, :name, :created_at, :updated_at)
        SQL;

        $stmt = $connection->prepare($query);

        $parameters = [
            'id' => Uuid::fromHexToBytes($id),
            'name' => $name,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'updated_at' => null,
        ];

        $stmt->execute($parameters);
    }
}
