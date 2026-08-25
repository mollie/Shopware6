<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;

final class FakeLanguageRepository extends EntityRepository
{
    private LanguageCollection $collection;

    /** @var list<mixed> */
    private array $requestedIds = [];

    /**
     * Without a locale code the repository behaves like a lookup that found no language, which is
     * what LocaleProvider falls back to en-GB for.
     */
    public function __construct(?string $localeCode = null)
    {
        $this->collection = new LanguageCollection();

        if ($localeCode === null) {
            return;
        }

        $locale = new LocaleEntity();
        $locale->setId('locale-id');
        $locale->setCode($localeCode);

        $language = new LanguageEntity();
        $language->setId(Context::createDefaultContext()->getLanguageId());
        $language->setLocale($locale);

        $this->collection->add($language);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $this->requestedIds = array_values(array_merge($this->requestedIds, $criteria->getIds()));

        return new EntitySearchResult(LanguageDefinition::ENTITY_NAME, $this->collection->count(), $this->collection, null, $criteria, $context);
    }

    /**
     * @return list<mixed>
     */
    public function getRequestedIds(): array
    {
        return $this->requestedIds;
    }
}
