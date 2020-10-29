<?php

namespace Kiener\MolliePayments\Subscriber;

use Exception;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;




class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{
    /** @var MollieApiClient */
    private $apiClient;

    /** @var SettingsService */
    private $settingsService;

    /** @var EntityRepositoryInterface */
    private $languageRepositoryInterface;

    /** @var EntityRepositoryInterface */
    private $localeRepositoryInterface;


    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     * * The method name to call (priority defaults to 0)
     * * An array composed of the method name to call and the priority
     * * An array of arrays composed of the method names to call and respective
     *   priorities, or 0 if unset
     *
     * For instance:
     *
     * * array('eventName' => 'methodName')
     * * array('eventName' => array('methodName', $priority))
     * * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'addDataToPage',
        ];
    }

    /**
     * Creates a new instance of the checkout confirm page subscriber.
     *
     * @param MollieApiClient $apiClient
     * @param SettingsService $settingsService
     */
    public function __construct(
        MollieApiClient $apiClient,
        SettingsService $settingsService,
        EntityRepositoryInterface $languageRepositoryInterface,
        EntityRepositoryInterface $localeRepositoryInterface

    )
    {
        $this->apiClient = $apiClient;
        $this->settingsService = $settingsService;
        $this->languageRepositoryInterface = $languageRepositoryInterface;
        $this->localeRepositoryInterface = $localeRepositoryInterface;
    }

    /**
     * @param PageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    public function addDataToPage($args): void
    {
        $this->addMollieLocaleVariableToPage($args);
        $this->addMollieProfileIdVariableToPage($args);
        $this->addMollieTestModeVariableToPage($args);
        $this->addMollieComponentsVariableToPage($args);
        $this->addMollieIdealIssuersVariableToPage($args);
    }

    /**
     * Adds the locale for Mollie components to the storefront.
     *
     * @param PageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    public function addMollieLocaleVariableToPage($args): void
    {


        /**
         * Build an array of available locales.
         */
        $availableLocales = [
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
         * Get the language object from the sales channel context.
         */
        $locale = '';

        $context = $args->getContext();
        $salesChannelContext = $args->getSalesChannelContext();

        if ($context !== null &&  $salesChannelContext !== null) {
            $salesChannel = $salesChannelContext->getSalesChannel();
            if ($salesChannel !== null) {
                $languageId = $salesChannel->getLanguageId();
                if ($languageId !== null) {
                $languageCriteria = new Criteria();
                $languageCriteria->addFilter(new EqualsFilter('id', $languageId));

                $languages = $this->languageRepositoryInterface->search($languageCriteria, $args->getContext());
                $localeId = $languages->first()->getLocaleId();
                $localeCriteria = new Criteria();
                $localeCriteria->addFilter(new EqualsFilter('id', $localeId));

                $locales = $this->localeRepositoryInterface->search($localeCriteria, $args->getContext());
                $locale = $locales->first()->getCode();

                }
            }
        }



        /**
         * Set the locale based on the current storefront.
         */


        if ($locale !== null && $locale !== '') {
            $locale = str_replace('-', '_', $locale);
        }

        /**
         * Check if the shop locale is available.
         */
        if ($locale === '' || !in_array($locale, $availableLocales, true)) {
            $locale = 'en_GB';
        }


        $args->getPage()->assign([
            'mollie_locale' => $locale,
        ]);
    }

    /**
     * Adds the test mode variable to the storefront.
     *
     * @param PageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    public function addMollieTestModeVariableToPage($args): void
    {
        /** @var MollieSettingStruct $settings */
        $settings = $this->settingsService->getSettings(
            $args->getSalesChannelContext()->getSalesChannel()->getId(),
            $args->getContext()
        );

        $args->getPage()->assign([
            'mollie_test_mode' => $settings->isTestMode() ? 'true' : 'false',
        ]);
    }

    /**
     * Adds the profile id to the storefront.
     *
     * @param PageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    public function addMollieProfileIdVariableToPage($args): void
    {
        /** @var string $mollieProfileId */
        $mollieProfileId = '';

        /**
         * Fetches the profile id from Mollie's API for the current key.
         */
        try {
            if ($this->apiClient->usesOAuth() === false) {
                $mollieProfile = $this->apiClient->profiles->get('me');
            } else {
                $mollieProfile = $this->apiClient->profiles->page()->offsetGet(0);
            }

            if (isset($mollieProfile->id)) {
                $mollieProfileId = $mollieProfile->id;
            }
        } catch (ApiException $e) {
            //
        }

        $args->getPage()->assign([
            'mollie_profile_id' => $mollieProfileId,
        ]);
    }

    /**
     * Adds the components variable to the storefront.
     *
     * @param PageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    public function addMollieComponentsVariableToPage($args)
    {
        /** @var MollieSettingStruct $settings */
        $settings = $this->settingsService->getSettings(
            $args->getSalesChannelContext()->getSalesChannel()->getId(),
            $args->getContext()
        );

        $args->getPage()->assign([
            'enable_credit_card_components' => $settings->getEnableCreditCardComponents(),
        ]);
    }

    /**
     * Adds ideal issuers variable to the storefront.
     *
     * @param PageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    public function addMollieIdealIssuersVariableToPage($args)
    {
        /** @var array $customFields */
        $customFields = [];

        /** @var Method $ideal */
        $ideal = null;

        /** @var string $mollieProfileId */
        $mollieProfileId = '';

        /** @var string $preferredIssuer */
        $preferredIssuer = '';

        /**
         * Fetches the profile id from Mollie's API for the current key.
         */
        try {
            if ($this->apiClient->usesOAuth() === false) {
                $mollieProfile = $this->apiClient->profiles->get('me');
            } else {
                $mollieProfile = $this->apiClient->profiles->page()->offsetGet(0);
            }

            if (isset($mollieProfile->id)) {
                $mollieProfileId = $mollieProfile->id;
            }
        } catch (ApiException $e) {
            //
        }

        // Get custom fields from the customer in the sales channel context
        if ($args->getSalesChannelContext()->getCustomer() !== null) {
            $customFields = $args->getSalesChannelContext()->getCustomer()->getCustomFields();
        }

        // Get the preferred issuer from the custom fields
        if (
            is_array($customFields)
            && isset($customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][CustomerService::CUSTOM_FIELDS_KEY_PREFERRED_IDEAL_ISSUER])
            && (string)$customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][CustomerService::CUSTOM_FIELDS_KEY_PREFERRED_IDEAL_ISSUER] !== ''
        ) {
            $preferredIssuer = $customFields[CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS][CustomerService::CUSTOM_FIELDS_KEY_PREFERRED_IDEAL_ISSUER];
        }

        /** @var array $parameters */
        $parameters = [
            'include' => 'issuers',
        ];

        if ($this->apiClient->usesOAuth()) {
            $parameters['profileId'] = $mollieProfileId;
        }

        // Get issuers from the API
        try {
            $ideal = $this->apiClient->methods->get(PaymentMethod::IDEAL, $parameters);
        } catch (Exception $e) {
            //
        }

        // Assign issuers to storefront
        if ($ideal !== null) {
            $args->getPage()->assign([
                'ideal_issuers' => $ideal->issuers,
                'preferred_issuer' => $preferredIssuer,
            ]);
        }
    }
}
