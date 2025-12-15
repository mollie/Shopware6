<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Fixtures\Utils;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;

class TaxUtils
{
    /**
     * @var EntityRepository<TaxCollection>
     */
    private EntityRepository $taxRepository;

    /**
     * @param EntityRepository<TaxCollection> $taxRepository
     */
    public function __construct(EntityRepository $taxRepository)
    {
        $this->taxRepository = $taxRepository;
    }

    /**
     * Return the tax entity with a tax rate of 19% or null if none exists.
     */
    public function getTax19(): ?TaxEntity
    {
        return $this->getTax(19);
    }

    /**
     * Return a tax entity with a specific tax rate or null if none exists.
     */
    public function getTax(float $taxRate): ?TaxEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('taxRate', $taxRate))
            ->setLimit(1)
        ;

        $criteria->setTitle(\sprintf('%s::%s()', __CLASS__, __FUNCTION__));

        $tax = $this->taxRepository
            ->search($criteria, Context::createDefaultContext())
            ->first()
        ;

        return $tax instanceof TaxEntity ? $tax : null;
    }
}
