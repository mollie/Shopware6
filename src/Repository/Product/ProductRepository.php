<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Product;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductRepository implements ProductRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $repoProducts;


    /**
     * @param EntityRepository $repoProducts
     */
    public function __construct($repoProducts)
    {
        $this->repoProducts = $repoProducts;
    }

    /**
     * @param string $productId
     * @param SalesChannelContext $context
     * @return array<ProductEntity>
     */
    public function findByID(string $productId, SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $productId));

        $products = $this->repoProducts->search($criteria, $context->getContext());

        /** @var array<ProductEntity> $elements */
        $elements = $products->getElements();

        return $elements;
    }

    /**
     * @param string $productNumber
     * @param SalesChannelContext $context
     * @return array<ProductEntity>
     */
    public function findByNumber(string $productNumber, SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', $productNumber));

        $products = $this->repoProducts->search($criteria, $context->getContext());

        /** @var array<ProductEntity> $elements */
        $elements = $products->getElements();

        return $elements;
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->repoProducts->search($criteria, $context);
    }
}
