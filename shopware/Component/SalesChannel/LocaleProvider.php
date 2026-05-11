<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\SalesChannel;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class LocaleProvider
{
    /** @var array<string, string> */
    private array $cache = [];

    /**
     * @param EntityRepository<LanguageCollection<LanguageEntity>> $languageRepository
     */
    public function __construct(
        #[Autowire(service: 'language.repository')]
        private readonly EntityRepository $languageRepository,
    ) {
    }

    public function getLocaleCode(string $languageId, Context $context): string
    {
        if (isset($this->cache[$languageId])) {
            return $this->cache[$languageId];
        }

        $criteria = new Criteria([$languageId]);
        $criteria->addAssociation('locale');

        /** @var ?LanguageEntity $language */
        $language = $this->languageRepository->search($criteria, $context)->first();

        $localeCode = 'en-GB';

        if ($language instanceof LanguageEntity) {
            $locale = $language->getLocale();
            if ($locale instanceof LocaleEntity) {
                $localeCode = $locale->getCode();
            }
        }

        $this->cache[$languageId] = $localeCode;

        return $localeCode;
    }
}
