<?php

namespace MolliePayments\Tests\Fakes\Repositories;

use Kiener\MolliePayments\Repository\Product\ProductRepositoryInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;


class FakeProductRepository implements ProductRepositoryInterface
{
    /**
     * @var ?ProductEntity
     */
    private $searchResultID;

    /**
     * @var ?ProductEntity
     */
    private $searchResultNumber;


    /**
     * @param ProductEntity|null $resultID
     * @param ProductEntity|null $resultNumber
     */
    public function __construct(?ProductEntity $resultID, ?ProductEntity $resultNumber)
    {
        $this->searchResultID = $resultID;
        $this->searchResultNumber = $resultNumber;
    }

    /**
     * @param string $productId
     * @param SalesChannelContext $context
     * @return array<ProductEntity>
     */
    public function findByID(string $productId, SalesChannelContext $context): array
    {
        if ($this->searchResultID === null) {
            return [];
        }

        return [$this->searchResultID];
    }

    /**
     * @param string $productNumber
     * @param SalesChannelContext $context
     * @return array<ProductEntity>
     */
    public function findByNumber(string $productNumber, SalesChannelContext $context): array
    {
        if ($this->searchResultNumber === null) {
            return [];
        }

        return [$this->searchResultNumber];
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        // TODO: Implement search() method.
    }

}
