<?php
declare(strict_types=1);

namespace Mollie\Integration\Data;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

trait ProductTestBehaviour
{
    use IntegrationTestBehaviour;

    public function getProductByNumber(string $productNumber, Context $context): ProductEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('product.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('tax');
        $criteria->addAssociation('manufacturer.media');
        $criteria->addAssociation('media');

        $criteria->addFilter(new EqualsFilter('productNumber', $productNumber));

        return $repository->search($criteria, $context)->first();
    }
}
