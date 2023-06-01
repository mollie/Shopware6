<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Language;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class LanguageRepository implements LanguageRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $languageRepository;

    /**
     * @param EntityRepository $languageRepository
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
}
