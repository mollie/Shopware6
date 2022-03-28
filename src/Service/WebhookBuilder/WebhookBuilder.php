<?php

namespace Kiener\MolliePayments\Service\WebhookBuilder;

use Kiener\MolliePayments\Service\SettingsService;
use Symfony\Component\Routing\RouterInterface;


class WebhookBuilder
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var SettingsService
     */
    private $pluginSettings;


    /**
     * @param RouterInterface $router
     * @param SettingsService $pluginSettings
     */
    public function __construct(RouterInterface $router, SettingsService $pluginSettings)
    {
        $this->router = $router;
        $this->pluginSettings = $pluginSettings;
    }


    /**
     * @param string $transactionId
     * @return string
     */
    public function buildWebhook(string $transactionId): string
    {
        $params = [
            'transactionId' => $transactionId
        ];

        $webhookUrl = $this->router->generate(
            'frontend.mollie.webhook',
            $params,
            $this->router::ABSOLUTE_URL
        );


        $customDomain = $this->pluginSettings->getEnvMollieShopDomain();

        if ($customDomain !== '') {

            $components = parse_url($webhookUrl);

            # replace old domain with new custom domain
            $webhookUrl = str_replace((string)$components['host'], $customDomain, $webhookUrl);
        }

        return $webhookUrl;
    }

    /**
     * @param string $subscriptionId
     * @return string
     */
    public function buildSubscriptionWebhook(string $subscriptionId): string
    {
        $webhookUrl = $this->router->generate(
            'frontend.mollie.webhook.subscription.renew',
            ['swSubscriptionId' => $subscriptionId],
            $this->router::ABSOLUTE_URL
        );

        $customDomain = $this->pluginSettings->getEnvMollieShopDomain();

        if ($customDomain !== '') {

            $components = parse_url($webhookUrl);

            # replace old domain with new custom domain
            $webhookUrl = str_replace((string)$components['host'], $customDomain, $webhookUrl);
        }

        return $webhookUrl;
    }

}
