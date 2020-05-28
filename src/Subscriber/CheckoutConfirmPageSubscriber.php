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

class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{
    /** @var MollieApiClient */
    private $apiClient;

    /** @var SettingsService */
    private $settingsService;

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
        SettingsService $settingsService
    )
    {
        $this->apiClient = $apiClient;
        $this->settingsService = $settingsService;
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
        $language = $args->getSalesChannelContext()->getSalesChannel()->getLanguage();

        /**
         * Set the locale based on the current storefront.
         */
        $locale = '';

        if ($language !== null && $language->getLocale() !== null) {
            $locale = str_replace('-', '_', $language->getLocale()->getCode());
        }

        /**
         * Check if the shop locale is available.
         */
        if ($locale === '' || !in_array($locale, $availableLocales, true)) {
            $locale = 'en_US';
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
            file_put_contents(__DIR__ . '/errors.txt', $e->getMessage());
        }

        // Assign issuers to storefront
        if ($ideal !== null) {
            file_put_contents(__DIR__ . '/issuers.txt', print_r($ideal->issuers, true));
            $args->getPage()->assign([
                'ideal_issuers' => $ideal->issuers,
                'preferred_issuer' => $preferredIssuer,
            ]);
        }
    }
}