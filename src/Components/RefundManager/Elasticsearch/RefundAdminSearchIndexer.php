<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\Elasticsearch;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;

class RefundAdminSearchIndexer extends AbstractAdminIndexer
{
    private Connection $connection;
    private IteratorFactory $factory;
    private EntityRepository $repository;
    private int $indexingBatchSize;

    /**
     * elasticsearch below 6.6 install old doctrine dbal where binary type does not exists yet
     */
    private const TYPE_BINARY = ParameterType::BINARY + Connection::ARRAY_PARAM_OFFSET;

    /**
     * @internal
     */
    public function __construct(
        Connection       $connection,
        IteratorFactory  $factory,
        EntityRepository $repository,
        int              $indexingBatchSize
    ) {
        $this->connection = $connection;
        $this->factory = $factory;
        $this->repository = $repository;
        $this->indexingBatchSize = $indexingBatchSize;
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
     * @param array<int, array<string>>|array<string> $ids
     *
     * @return array<string, array<string, string>>
     */
    public function fetch(array $ids): array
    {
        $data = $this->connection->fetchAllAssociative(
            '
            SELECT LOWER(HEX(mollie_refund.id)) as id,
                   type,
                   public_description,
                   internal_description,
             
            FROM mollie_refund
            WHERE mollie_refund.id IN (:ids)
            GROUP BY mollie_refund.id
        ',
            [
                'ids' => Uuid::fromHexToBytesList($ids),
            ],
            [
                'ids' => self::TYPE_BINARY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $id = (string)$row['id'];
            $text = \implode(' ', array_filter($row));
            $mapped[$id] = ['id' => $id, 'text' => \strtolower($text)];
        }

        return $mapped;
    }


    /**
     * @param array<string, mixed> $result
     *
     * @return array{total:int, data:EntityCollection<Entity>}
     *
     * Return EntityCollection<Entity> and their total by ids in the result parameter
     */
    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => (int)$result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }
}
