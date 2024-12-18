<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Language;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;

class LanguageRepository implements LanguageRepositoryInterface
{
    /**
     * @var EntityRepository<LanguageCollection>
     */
    private $languageRepository;

    /**
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct($languageRepository)
    {
        $this->languageRepository = $languageRepository;
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->languageRepository->search($criteria, $context);
    }

    /**
     * @param string $languageId
     * @param Context $context
     * @return null|LanguageEntity
     */
    public function findById(string $languageId, Context $context): ?LanguageEntity
    {
        $languageCriteria = new Criteria();
        $languageCriteria->addAssociation('locale');
        $languageCriteria->addFilter(new EqualsFilter('id', $languageId));

        $languagesResult = $this->search($languageCriteria, $context);

        return $languagesResult->first();
    }
}
