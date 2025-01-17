<?php

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ProductService
{
    /**
     * @var EntityRepository
     */
    private $productRepository;


    /**
     * @param EntityRepository $productRepository
     */
    public function __construct(EntityRepository $productRepository)
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
