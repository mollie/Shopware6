<?php

namespace MolliePayments\Tests\Fakes\Repositories;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Language\LanguageEntity;

class FakeLanguageRepository extends EntityRepository
{

    /**
     * @var ?LanguageEntity
     */
    private $foundLanguage;


    /**
     * @param null|LanguageEntity $foundLanguage
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
        $entities = new EntityCollection();
        if ($this->foundLanguage instanceof LanguageEntity) {
            $entities->add($this->foundLanguage);
        }
        return new EntitySearchResult(LanguageEntity::class, $entities->count(), $entities, null, $criteria, $context);
    }

    /**
     * @param string $languageId
     * @param Context $context
     * @return null|LanguageEntity
     */
    public function findById(string $languageId, Context $context): ?LanguageEntity
    {
        return $this->foundLanguage;
    }
}
