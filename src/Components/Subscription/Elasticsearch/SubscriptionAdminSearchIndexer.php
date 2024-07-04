<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Elasticsearch;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;

class SubscriptionAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly IteratorFactory $factory,
        private readonly EntityRepository $repository,
        private readonly int $indexingBatchSize
    ) {
    }

    public function getDecorated(): AbstractAdminIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function getName(): string
    {
        return 'mollie_subscription';
    }

    public function getEntity(): string
    {
        return SubscriptionDefinition::ENTITY_NAME;
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function fetch(array $ids): array
    {
        $data = $this->connection->fetchAllAssociative(
            '
            SELECT LOWER(HEX(mollie_subscription.id)) as id,
                   mollie_id,
                   mollie_customer_id,
                   description,
                   next_payment_at,
                   last_reminded_at,
                   canceled_at,
                   mandate_id,
                   status
            FROM mollie_subscription
            WHERE mollie_subscription.id IN (:ids)
            GROUP BY mollie_subscription.id
        ',
            [
                'ids' => Uuid::fromHexToBytesList($ids),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $id = (string) $row['id'];
            $text = \implode(' ', array_filter($row));
            $mapped[$id] = ['id' => $id, 'text' => \strtolower($text)];
        }

        return $mapped;
    }

    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }
}
