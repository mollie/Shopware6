<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Country;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\Country\CountryCollection;

class CountryRepository implements CountryRepositoryInterface
{
    /**
     * @var EntityRepository<CountryCollection>
     */
    private $countryRepository;

    /**
     * @param EntityRepository<CountryCollection> $countryRepository
     */
    public function __construct($countryRepository)
    {
        $this->countryRepository = $countryRepository;
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->countryRepository->searchIds($criteria, $context);
    }
}
