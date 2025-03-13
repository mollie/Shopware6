<?php
declare(strict_types=1);

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

    public function __construct(EntityRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * Finds a product by id.
     *
     * @param string $productId
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function getProductById($productId, Context $context = null): ?ProductEntity
    {
        $criteria = new Criteria([$productId]);

        $result = $this->productRepository->search($criteria, $context ?? Context::createDefaultContext());

        /** @var null|ProductEntity */
        return $result->get($productId);
    }
}
