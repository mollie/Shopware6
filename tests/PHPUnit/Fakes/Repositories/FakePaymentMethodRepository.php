<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Fakes\Repositories;

use Kiener\MolliePayments\Repository\PaymentMethodRepository;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FakePaymentMethodRepository extends PaymentMethodRepository
{
    /**
     * @var PaymentMethodEntity
     */
    private $entity;

    public function __construct(PaymentMethodEntity $entity)
    {
        $this->entity = $entity;
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return new IdSearchResult(
            1,
            [
                [
                    'primaryKey' => $this->entity->getId(),
                    'data' => [],
                ],
            ],
            $criteria,
            $context
        );
    }

    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return new EntitySearchResult(
            PaymentMethodEntity::class,
            1,
            new EntityCollection([$this->entity]),
            null,
            $criteria,
            $context
        );
    }

    public function getActiveApplePayID(Context $context): string
    {
        return 'phpunit-id';
    }

    public function getActivePaypalExpressID(SalesChannelContext $context): string
    {
        return 'phpunit-id';
    }
}
