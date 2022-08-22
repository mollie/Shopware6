<?php

namespace Kiener\MolliePayments\Service\Router;

use Symfony\Component\Routing\RouterInterface;


class RoutingBuilder
{

    /**
     *
     */
    private const ADMIN_DOMAIN_ENV_KEY = 'APP_URL';
    /**
     *
     */
    private const CUSTOM_DOMAIN_ENV_KEY = 'MOLLIE_SHOP_DOMAIN';


    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var RoutingDetector
     */
    private $routingDetector;


    /**
     * @param RouterInterface $router
     * @param RoutingDetector $routingDetector
     */
    public function __construct(RouterInterface $router, RoutingDetector $routingDetector)
    {
        $this->router = $router;
        $this->routingDetector = $routingDetector;
    }


    /**
     * @param string $transactionId
     * @return string
     */
    public function buildRedirectURL(string $transactionId): string
    {
        $isStoreApiCall = $this->routingDetector->isStoreApiRoute();

        $params = [
            'transactionId' => $transactionId
        ];

        # only go to an API domain if we are working in headless scopes.
        # otherwise stick with the existing storefront route.
        # if we do not do this, we have weird redirect problems.
        # important is, that we only update the domains in case of custom domains
        # if we are in the headless scope, storefront should always be as it is.
        if ($isStoreApiCall) {
            $redirectUrl = $this->router->generate('api.mollie.payment-return', $params, $this->router::ABSOLUTE_URL);
            $redirectUrl = $this->applyAdminDomain($redirectUrl);
        } else {
            $redirectUrl = $this->router->generate('frontend.mollie.payment', $params, $this->router::ABSOLUTE_URL);
        }

        return $redirectUrl;
    }

    /**
     * @param string $transactionId
     * @return string
     */
    public function buildWebhookURL(string $transactionId): string
    {
        $isStoreApiCall = $this->routingDetector->isStoreApiRoute();

        $params = [
            'transactionId' => $transactionId
        ];

        # if we are in headless, then we use the api webhook
        # we could also use that one for storefront shops,
        # but I'm a bit scared of breaking changes with running shops.
        # they are used to this approach, so let's keep that for now.
        # but in both cases, update the domain if we have custom settings.
        if ($isStoreApiCall) {
            $webhookUrl = $this->router->generate('api.mollie.webhook', $params, $this->router::ABSOLUTE_URL);
            $webhookUrl = $this->applyAdminDomain($webhookUrl);
        } else {
            $webhookUrl = $this->router->generate('frontend.mollie.webhook', $params, $this->router::ABSOLUTE_URL);
        }

        $webhookUrl = $this->applyCustomDomain($webhookUrl);

        return $webhookUrl;
    }

    /**
     * @param string $url
     * @return string
     */
    private function applyAdminDomain(string $url): string
    {
        $adminDomain = trim((string)getenv(self::ADMIN_DOMAIN_ENV_KEY));
        $adminDomain = str_replace('http://', '', $adminDomain);
        $adminDomain = str_replace('https://', '', $adminDomain);

        $replaceDomain = '';

        # if we have an admin domain that is not localhost
        # but a real one, then use this
        if ($adminDomain !== '' && $adminDomain !== 'localhost') {
            $replaceDomain = $adminDomain;
        }

        if ($replaceDomain !== '') {
            $components = parse_url($url);
            # replace old domain with new custom domain
            $url = str_replace((string)$components['host'], $replaceDomain, $url);
        }

        return $url;
    }

    /**
     * @param string $url
     * @return string
     */
    private function applyCustomDomain(string $url): string
    {
        $customDomain = trim((string)getenv(self::CUSTOM_DOMAIN_ENV_KEY));

        $replaceDomain = '';

        # if we still have a custom domain in the .ENV
        # then always use this one and override existing ones
        if ($customDomain !== '') {
            $replaceDomain = $customDomain;
        }

        if ($replaceDomain !== '') {
            $components = parse_url($url);
            # replace old domain with new custom domain
            $url = str_replace((string)$components['host'], $replaceDomain, $url);
        }

        return $url;
    }

}
