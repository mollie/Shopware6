<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Search;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundCollection;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundDefinition;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('shopware.elastic.admin-searcher-index', ['key' => 'mollie_refund'])]
final class RefundAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @param EntityRepository<RefundCollection<RefundEntity>> $repository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly IteratorFactory $factory,
        #[Autowire(service: 'mollie_refund.repository')]
        private readonly EntityRepository $repository,
        #[Autowire(value: '%elasticsearch.indexing_batch_size%')]
        private readonly int $indexingBatchSize
    ) {
    }

    public function getDecorated(): AbstractAdminIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function getName(): string
    {
        return 'mollie_refund';
    }

    public function getEntity(): string
    {
        return RefundDefinition::ENTITY_NAME;
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string, array<string, string>>
     */
    public function fetch(array $ids): array
    {
        $data = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(mollie_refund.id)) as id,
                    type,
                    public_description,
                    internal_description
             FROM mollie_refund
             WHERE mollie_refund.id IN (:ids)
             GROUP BY mollie_refund.id',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $mapped = [];
        foreach ($data as $row) {
            $id = (string) $row['id'];
            $text = implode(' ', array_filter($row));
            $mapped[$id] = ['id' => $id, 'text' => strtolower($text)];
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array{total:int, data:EntityCollection<RefundEntity>}
     */
    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }
}
