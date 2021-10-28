<?php

namespace Kiener\MolliePayments\Repository\Product;

use Shopware\Core\Content\Product\ProductEntity;
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

}
