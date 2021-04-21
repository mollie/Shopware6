<?php

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ProductService
{
    /** @var EntityRepositoryInterface */
    private $productRepository;

    /**
     * Creates a new instance of the product service.
     *
     * @param EntityRepositoryInterface $productRepository
     */
    public function __construct(
        EntityRepositoryInterface $productRepository
    )
    {
        $this->productRepository = $productRepository;
    }

    /**
     * Returns the product repository.
     *
     * @return EntityRepositoryInterface
     */
    public function getRepository(): EntityRepositoryInterface
    {
        return $this->productRepository;
    }

    /**
     * Finds a product by id.
     *
     * @param $productId
     * @param Context|null $context
     * @return ProductEntity|null
     * @throws InconsistentCriteriaIdsException
     */
    public function getProductById(
        $productId,
        Context $context = null
    ): ?ProductEntity
    {
        $criteria = new Criteria([$productId]);

        return $this->getRepository()->search(
            $criteria,
            $context ?? Context::createDefaultContext()
        )->get($productId);
    }
}