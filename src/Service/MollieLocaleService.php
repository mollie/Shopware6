<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Repository\Language\LanguageRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MollieLocaleService
{
    public const AVAILABLE_LOCALES = [
        'en_US',
        'en_GB',
        'nl_NL',
        'fr_FR',
        'it_IT',
        'de_DE',
        'de_AT',
        'de_CH',
        'es_ES',
        'ca_ES',
        'nb_NO',
        'pt_PT',
        'sv_SE',
        'fi_FI',
        'da_DK',
        'is_IS',
        'hu_HU',
        'pl_PL',
        'lv_LV',
        'lt_LT'
    ];

    /**
     * @var LanguageRepositoryInterface
     */
    private $repoLanguages;

    public function __construct(LanguageRepositoryInterface $repoLanguages)
    {
        $this->repoLanguages = $repoLanguages;
    }

    /**
     * @param SalesChannelContext $salesChannelContext
     * @return string
     */
    public function getLocale(SalesChannelContext $salesChannelContext): string
    {
        # Get the language object from the sales channel context.
        $locale = '';

        $salesChannel = $salesChannelContext->getSalesChannel();
        $languageId = $salesChannel->getLanguageId();

        $language = $this->repoLanguages->findById($languageId, $salesChannelContext->getContext());

        if ($language !== null && $language->getLocale() !== null) {
            $locale = $language->getLocale()->getCode();
        }

        return $this->getMollieLocale($locale);
    }

    public function getMollieLocale(?string $locale): string
    {
        # Set the locale based on the current storefront.
        if ($locale !== null && $locale !== '') {
            $locale = str_replace('-', '_', $locale);
        }

        # Check if the shop locale is available.
        if ($locale === '' || !in_array($locale, self::AVAILABLE_LOCALES, true)) {
            $locale = 'en_GB';
        }

        return $locale;
    }
}
