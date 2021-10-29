<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Product;


use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;


class ProductRepository implements ProductRepositoryInterface
{

    /**
     * @var EntityRepositoryInterface
     */
    private $repoProducts;


    /**
     * @param EntityRepositoryInterface $repoProducts
     */
    public function __construct(EntityRepositoryInterface $repoProducts)
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

        return $products->getElements();
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

        return $products->getElements();
    }

}