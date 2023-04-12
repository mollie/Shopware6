<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Locale;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class LocaleRepository implements LocaleRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $localeRepository;

    /**
     * @param EntityRepository $localeRepository
     */
    public function __construct($localeRepository)
    {
        $this->localeRepository = $localeRepository;
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->localeRepository->search($criteria, $context);
    }
}
