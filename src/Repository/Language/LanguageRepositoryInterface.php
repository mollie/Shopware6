<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Language;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Language\LanguageEntity;

interface LanguageRepositoryInterface
{
    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult;

    /**
     * @param string $languageId
     * @param Context $context
     * @return null|LanguageEntity
     */
    public function findById(string $languageId, Context $context): ?LanguageEntity;
}
