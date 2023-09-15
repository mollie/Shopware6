<?php

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Repository\Product\ProductRepositoryInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ProductService
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;


    /**
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * Finds a product by id.
     *
     * @param string $productId
     * @param null|Context $context
     * @throws InconsistentCriteriaIdsException
     * @return null|ProductEntity
     */
    public function getProductById($productId, Context $context = null): ?ProductEntity
    {
        $criteria = new Criteria([$productId]);

        $result = $this->productRepository->search($criteria, $context ?? Context::createDefaultContext());

        /** @var null|ProductEntity $product */
        $product = $result->get($productId);

        return $product;
    }
}
