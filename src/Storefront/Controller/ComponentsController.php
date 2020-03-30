<?php

namespace Kiener\MolliePayments\Storefront\Controller;

use Kiener\MolliePayments\Config\Config;
use Kiener\MolliePayments\Service\CustomerService;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ComponentsController extends StorefrontController
{
    /** @var Config */
    protected $config;

    /** @var CustomerService */
    protected $customerService;

    /** @var MollieApiClient */
    protected $apiClient;

    public function __construct(
        Config $config,
        CustomerService $customerService,
        MollieApiClient $apiClient
    )
    {
        $this->config = $config;
        $this->customerService = $customerService;
        $this->apiClient = $apiClient;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/components/store-card-token/{customerId}/{cardToken}", name="frontend.mollie.components.storeCardToken", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @param string $customerId
     * @param string $cardToken
     * @return Response
     */
    public function storeCardToken(SalesChannelContext $context, string $customerId, string $cardToken): Response
    {
        $result = null;

        /** @var CustomerEntity $customer */
        $customer = $this->customerService->getCustomer($customerId, $context->getContext());

        if ($customer !== null) {
            $result = $this->customerService->setCardToken(
                $customer,
                $cardToken,
                $context->getContext()
            );
        }

        /**
         * Output the json result.
         */
        return new Response(json_encode([
            'success' => (bool) $result,
            'customerId' => $customerId,
            'result' => $result->getErrors()
        ]), 200, [
            'Content-Type' => 'text/javascript'
        ]);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/components/js/{type}", name="frontend.mollie.components.js", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @param string $type
     * @return Response
     */
    public function componentsJs(SalesChannelContext $context, string $type): Response
    {
        // Variables
        $javascript = '';
        $mollieProfileId = '';

        /**
         * Fetches the profile id from Mollie's API for the current key.
         */
        try {
            $mollieProfile = $this->apiClient->profiles->get('me');

            if (isset($mollieProfile->id)) {
                $mollieProfileId = $mollieProfile->id;
            }
        } catch (ApiException $e) {
            //
        }

        /**
         * Get the contents of the base javascript file.
         */
        if ($type === 'creditcard') {
            $javascript = file_get_contents(__DIR__ . '/../../Resources/assets/js/components.creditcard.js');
        }

        /**
         * Replace variables in the javascript file.
         */
        $javascript = str_replace('[mollie_profile_id]', $mollieProfileId, $javascript);
        $javascript = str_replace('[mollie_locale]', $this->getLocale($context), $javascript);
        $javascript = str_replace('[mollie_testmode]', $this->config::testMode() === true ? 'true' : 'false', $javascript);

        /**
         * Output the javascript code.
         */
        return new Response($javascript, 200, [
            'Content-Type' => 'text/javascript'
        ]);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/mollie/components/css/{type}", name="frontend.mollie.components.css", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @param string $type
     * @return Response
     */
    public function componentsCss(SalesChannelContext $context, string $type): Response
    {
        $css = '';

        /**
         * Get the contents of the css file.
         */
        if ($type === 'creditcard') {
            $stylesheet = file_get_contents(__DIR__ . '/../../Resources/assets/css/components.creditcard.css');
        }
        /**
         * Output the css stylesheet.
         */
        return new Response($stylesheet, 200, [
            'Content-Type' => 'text/css'
        ]);
    }

    /**
     * Get the locale for Mollie components.
     *
     * @param SalesChannelContext $context
     * @return string
     */
    private function getLocale(SalesChannelContext $context): string
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
        $language = $context->getSalesChannel()->getLanguage();

        /**
         * Set the locale based on the current storefront.
         */
        $locale = '';

        if ($language !== null && $language->getLocale() !== null) {
            $locale = $language->getLocale()->getCode();
        }

        /**
         * Check if the shop locale is available.
         */
        if ($locale === '' || !in_array($locale, $availableLocales, true)) {
            $locale = 'en_US';
        }

        return $locale;
    }
}