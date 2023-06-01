<?php

namespace Kiener\MolliePayments\Service\Router;

use Kiener\MolliePayments\Service\PluginSettingsServiceInterface;
use Symfony\Component\Routing\RouterInterface;

class RoutingBuilder
{
    /**
     * This has to match the parameter from the Return Route annotations.
     * Otherwise, an exception is being thrown.
     */
    private const ROUTE_PARAM_RETURN_ID = 'swTransactionId';

    /**
     * This has to match the parameter from the Webhook Route annotations.
     * Otherwise, an exception is being thrown.
     */
    private const ROUTE_PARAM_WEBHOOK_ID = 'swTransactionId';

    /**
     * This has to match the parameter from the Subscription Renewal Route annotations.
     * Otherwise, an exception is being thrown.
     */
    private const ROUTE_PARAM_SUBSCRIPTION_RENEW_ID = 'swSubscriptionId';

    /**
     * This has to match the parameter from the Subscription Update Payment Route annotations.
     * Otherwise, an exception is being thrown.
     */
    private const ROUTE_PARAM_SUBSCRIPTION_UPDATE_PAYMENT_ID = 'swSubscriptionId';


    /**
     * @var string
     */
    private $envAppUrl;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var RoutingDetector
     */
    private $routingDetector;

    /**
     * @var PluginSettingsServiceInterface
     */
    private $pluginSettings;


    /**
     * @param RouterInterface $router
     * @param RoutingDetector $routingDetector
     * @param PluginSettingsServiceInterface $pluginSettings
     * @param ?string $envAppUrl
     */
    public function __construct(RouterInterface $router, RoutingDetector $routingDetector, PluginSettingsServiceInterface $pluginSettings, ?string $envAppUrl)
    {
        $this->router = $router;
        $this->routingDetector = $routingDetector;
        $this->pluginSettings = $pluginSettings;
        $this->envAppUrl = (string)$envAppUrl;
    }


    /**
     * @param string $transactionId
     * @return string
     */
    public function buildReturnUrl(string $transactionId): string
    {
        $isStoreApiCall = $this->routingDetector->isStoreApiRoute();

        $params = [
            self::ROUTE_PARAM_RETURN_ID => $transactionId
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

        return (string)$redirectUrl;
    }

    /**
     * @param string $transactionId
     * @return string
     */
    public function buildWebhookURL(string $transactionId): string
    {
        $isStoreApiCall = $this->routingDetector->isStoreApiRoute();

        $params = [
            self::ROUTE_PARAM_WEBHOOK_ID => $transactionId
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

        $webhookUrl = $this->applyCustomDomain((string)$webhookUrl);

        return $webhookUrl;
    }

    /**
     * @param string $subscriptionId
     * @return string
     */
    public function buildSubscriptionWebhook(string $subscriptionId): string
    {
        $isStoreApiCall = $this->routingDetector->isStoreApiRoute();

        $params = [
            self::ROUTE_PARAM_SUBSCRIPTION_RENEW_ID => $subscriptionId
        ];

        if ($isStoreApiCall) {
            $webhookUrl = $this->router->generate('api.mollie.webhook_subscription', $params, $this->router::ABSOLUTE_URL);
            $webhookUrl = $this->applyAdminDomain($webhookUrl);
        } else {
            $webhookUrl = $this->router->generate('frontend.mollie.webhook.subscription', $params, $this->router::ABSOLUTE_URL);
        }

        $webhookUrl = $this->applyCustomDomain((string)$webhookUrl);

        return $webhookUrl;
    }

    /**
     * @param string $subscriptionId
     * @return string
     */
    public function buildSubscriptionPaymentUpdatedWebhook(string $subscriptionId): string
    {
        $isStoreApiCall = $this->routingDetector->isStoreApiRoute();

        if (!$isStoreApiCall) {
            return '';
        }

        $params = [
            self::ROUTE_PARAM_SUBSCRIPTION_UPDATE_PAYMENT_ID => $subscriptionId
        ];

        $webhookUrl = $this->router->generate('api.mollie.webhook_subscription_paymentmethod', $params, $this->router::ABSOLUTE_URL);
        $webhookUrl = $this->applyAdminDomain($webhookUrl);

        return $this->applyCustomDomain((string)$webhookUrl);
    }

    /**
     * @param string $subscriptionId
     * @return string
     */
    public function buildSubscriptionPaymentUpdatedReturnUrl(string $subscriptionId): string
    {
        $isStoreApiCall = $this->routingDetector->isStoreApiRoute();

        $params = [
            self::ROUTE_PARAM_SUBSCRIPTION_UPDATE_PAYMENT_ID => $subscriptionId
        ];

        if ($isStoreApiCall) {
            $webhookUrl = $this->router->generate('api.mollie.webhook_subscription_paymentmethod', $params, $this->router::ABSOLUTE_URL);
            $webhookUrl = $this->applyAdminDomain($webhookUrl);
        } else {
            $webhookUrl = $this->router->generate('frontend.account.mollie.subscriptions.payment.update-success', $params, $this->router::ABSOLUTE_URL);
        }

        return $this->applyCustomDomain((string)$webhookUrl);
    }

    /**
     * @param string $url
     * @return string
     */
    private function applyAdminDomain(string $url): string
    {
        $adminDomain = trim($this->envAppUrl);
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
            $host = (is_array($components) && isset($components['host'])) ? (string)$components['host'] : '';
            # replace old domain with new custom domain
            $url = str_replace($host, $replaceDomain, $url);
        }

        return $url;
    }

    /**
     * @param string $url
     * @return string
     */
    private function applyCustomDomain(string $url): string
    {
        $customDomain = trim($this->pluginSettings->getEnvMollieShopDomain());

        # if we still have a custom domain in the .ENV
        # then always use this one and override existing ones
        if ($customDomain !== '') {
            $components = parse_url($url);
            $host = (is_array($components) && isset($components['host'])) ? (string)$components['host'] : '';
            # replace old domain with new custom domain
            $url = str_replace($host, $customDomain, $url);
        }

        return $url;
    }
}
