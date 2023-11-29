<?php

namespace MolliePayments\Tests\Fakes\Repositories;

use Kiener\MolliePayments\Repository\Language\LanguageRepositoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Language\LanguageEntity;

class FakeLanguageRepository implements LanguageRepositoryInterface
{

    /**
     * @var ?LanguageEntity
     */
    private $foundLanguage;


    /**
     * @param LanguageEntity|null $foundLanguage
     */
    public function __construct(?LanguageEntity $foundLanguage)
    {
        $this->foundLanguage = $foundLanguage;
    }


    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        // TODO: Implement search() method.
    }

    /**
     * @param string $languageId
     * @param Context $context
     * @return LanguageEntity|null
     */
    public function findById(string $languageId, Context $context): ?LanguageEntity
    {
        return $this->foundLanguage;
    }

}
