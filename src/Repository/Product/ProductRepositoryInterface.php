<?php

namespace Kiener\MolliePayments\Repository\Product;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ProductRepositoryInterface
{
    /**
     * @param string $productId
     * @param SalesChannelContext $context
     * @return array<ProductEntity>
     */
    public function findByID(string $productId, SalesChannelContext $context): array;

    /**
     * @param string $productNumber
     * @param SalesChannelContext $context
     * @return array<ProductEntity>
     */
    public function findByNumber(string $productNumber, SalesChannelContext $context): array;

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult;
}
