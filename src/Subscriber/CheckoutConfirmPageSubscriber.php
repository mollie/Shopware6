<?php

namespace Kiener\MolliePayments\Subscriber;

use Exception;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Handler\Method\CreditCardPayment;
use Kiener\MolliePayments\Handler\Method\PosPayment;
use Kiener\MolliePayments\Repository\Language\LanguageRepositoryInterface;
use Kiener\MolliePayments\Repository\Locale\LocaleRepositoryInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\MandateServiceInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use Mollie\Api\Resources\Terminal;
use Mollie\Api\Types\PaymentMethod;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{
    /**
     * @var MollieApiFactory
     */
    private $apiFactory;

    /**
     * @var MollieApiClient
     */
    private $apiClient;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var MollieSettingStruct
     */
    private $settings;

    /**
     * @var LanguageRepositoryInterface
     */
    private $repoLanguages;

    /**
     * @var LocaleRepositoryInterface
     */
    private $repoLocales;

    /**
     * @var MandateServiceInterface
     */
    private $mandateService;

    /**
     * @var MollieGatewayInterface
     */
    private $mollieGateway;

    /**
     * @var ?string
     */
    private $profileId = null;

    /**
     * @return array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => [
                ['addDataToPage', 10],
            ],
            AccountEditOrderPageLoadedEvent::class => ['addDataToPage', 10],
        ];
    }

    /**
     * @param MollieApiFactory $apiFactory
     * @param SettingsService $settingsService
     * @param LanguageRepositoryInterface $languageRepositoryInterface
     * @param LocaleRepositoryInterface $localeRepositoryInterface
     * @param MandateServiceInterface $mandateService
     * @param MollieGatewayInterface $mollieGateway
     */
    public function __construct(MollieApiFactory $apiFactory, SettingsService $settingsService, LanguageRepositoryInterface $languageRepositoryInterface, LocaleRepositoryInterface $localeRepositoryInterface, MandateServiceInterface $mandateService, MollieGatewayInterface $mollieGateway)
    {
        $this->apiFactory = $apiFactory;
        $this->settingsService = $settingsService;
        $this->repoLanguages = $languageRepositoryInterface;
        $this->repoLocales = $localeRepositoryInterface;
        $this->mandateService = $mandateService;
        $this->mollieGateway = $mollieGateway;
    }


    /**
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function addDataToPage($args): void
    {
        # load our settings for the
        # current request
        $this->settings = $this->settingsService->getSettings($args->getSalesChannelContext()->getSalesChannel()->getId());

        $scId = $args->getSalesChannelContext()->getSalesChannel()->getId();
        $currentSelectedPaymentMethod = $args->getSalesChannelContext()->getPaymentMethod();
        $mollieAttributes = new PaymentMethodAttributes($currentSelectedPaymentMethod);

        # load additional data only for mollie payment methods
        if (! $mollieAttributes->isMolliePayment()) {
            return;
        }
        # now use our factory to get the correct
        # client with the correct sales channel settings
        $this->apiClient = $this->apiFactory->getClient($scId);

        $this->mollieGateway->switchClient($scId);

        $this->addMollieLocaleVariableToPage($args);
        $this->addMollieProfileIdVariableToPage($args);
        $this->addMollieTestModeVariableToPage($args);
        $this->addMollieComponentsVariableToPage($args);
        $this->addMollieIdealIssuersVariableToPage($args, $mollieAttributes);
        $this->addMollieSingleClickPaymentDataToPage($args, $mollieAttributes);
        $this->addMolliePosTerminalsVariableToPage($args, $mollieAttributes);
    }

    /**
     * Adds the locale for Mollie components to the storefront.
     *
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    private function addMollieLocaleVariableToPage($args): void
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


        $salesChannel = $salesChannelContext->getSalesChannel();
        if ($salesChannel !== null) {
            $languageId = $salesChannel->getLanguageId();
            if ($languageId !== null) {
                $languageCriteria = new Criteria();
                $languageCriteria->addFilter(new EqualsFilter('id', $languageId));

                $languages = $this->repoLanguages->search($languageCriteria, $args->getContext());

                $localeId = $languages->first()->getLocaleId();

                $localeCriteria = new Criteria();
                $localeCriteria->addFilter(new EqualsFilter('id', $localeId));

                $locales = $this->repoLocales->search($localeCriteria, $args->getContext());
                $locale = $locales->first()->getCode();
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
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    private function addMollieTestModeVariableToPage($args): void
    {
        $args->getPage()->assign([
            'mollie_test_mode' => $this->settings->isTestMode() ? 'true' : 'false',
        ]);
    }

    /**
     * Adds the profile id to the storefront.
     *
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    private function addMollieProfileIdVariableToPage($args): void
    {
        $mollieProfileId = $this->loadMollieProfileId();

        $args->getPage()->assign([
            'mollie_profile_id' => $mollieProfileId,
        ]);
    }

    private function loadMollieProfileId(): string
    {
        if ($this->profileId !== null) {
            return $this->profileId;
        }
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
        $this->profileId = $mollieProfileId;

        return $this->profileId;
    }

    /**
     * Adds the components variable to the storefront.
     *
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     */
    private function addMollieComponentsVariableToPage($args): void
    {
        $args->getPage()->assign([
            'enable_credit_card_components' => $this->settings->getEnableCreditCardComponents(),
            'enable_one_click_payments_compact_view' => $this->settings->isOneClickPaymentsCompactView(),
        ]);
    }

    /**
     * Adds ideal issuers variable to the storefront.
     *
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     * @param PaymentMethodAttributes $selectedPayment
     */
    private function addMollieIdealIssuersVariableToPage($args, $selectedPayment): void
    {
        // do not load ideal issuers if not required
        if ($selectedPayment->getMollieIdentifier() !== PaymentMethod::IDEAL) {
            return;
        }
        $customFields = [];
        $ideal = null;
        $preferredIssuer = '';

        $mollieProfileId = $this->loadMollieProfileId();

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
        if ($ideal instanceof Method) {
            $args->getPage()->assign([
                'ideal_issuers' => $ideal->issuers,
                'preferred_issuer' => $preferredIssuer,
            ]);
        }
    }

    /**
     * Adds ideal issuers variable to the storefront.
     *
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     * @param PaymentMethodAttributes $selectedPayment
     */
    private function addMolliePosTerminalsVariableToPage($args, $selectedPayment): void
    {
        //do not load terminals if not required
        if ($selectedPayment->getMollieIdentifier() !== PaymentMethod::POINT_OF_SALE) {
            return;
        }
        try {
            $terminalsArray = [];

            $terminals = $this->mollieGateway->getPosTerminals();

            foreach ($terminals as $terminal) {
                $terminalsArray[] = [
                    'id' => $terminal->id,
                    'name' => $terminal->description,
                ];
            }

            $args->getPage()->assign(
                [
                    'mollie_terminals' => $terminalsArray
                ]
            );
        } catch (Exception $e) {
        }
    }

    /**
     * Adds the components variable to the storefront.
     *
     * @param AccountEditOrderPageLoadedEvent|CheckoutConfirmPageLoadedEvent $args
     * @param PaymentMethodAttributes $selectedPayment
     */
    private function addMollieSingleClickPaymentDataToPage($args, $selectedPayment): void
    {
        // do not load credit card mandate if not required
        if ($selectedPayment->getMollieIdentifier() !== PaymentMethod::CREDITCARD) {
            return;
        }
        $args->getPage()->assign([
            'enable_one_click_payments' => $this->settings->isOneClickPaymentsEnabled(),
        ]);

        if (!$this->settings->isOneClickPaymentsEnabled()) {
            return;
        }

        try {
            $salesChannelContext = $args->getSalesChannelContext();
            $loggedInCustomer = $salesChannelContext->getCustomer();
            if (!$loggedInCustomer instanceof CustomerEntity) {
                return;
            }

            // only load the list of mandates if the payment method is CreditCardPayment
            if ($salesChannelContext->getPaymentMethod()->getHandlerIdentifier() !== CreditCardPayment::class) {
                return;
            }

            $mandates = $this->mandateService->getCreditCardMandatesByCustomerId($loggedInCustomer->getId(), $salesChannelContext);

            $args->getPage()->setExtensions([
                'MollieCreditCardMandateCollection' => $mandates
            ]);
        } catch (Exception $e) {
            //
        }
    }
}
