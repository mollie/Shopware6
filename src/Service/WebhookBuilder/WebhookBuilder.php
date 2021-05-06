<?php

namespace Kiener\MolliePayments\Service\WebhookBuilder;

use Symfony\Component\Routing\RouterInterface;


class WebhookBuilder
{

    /**
     *
     */
    private const CUSTOM_DOMAIN_ENV_KEY = 'MOLLIE_SHOP_DOMAIN';

    /**
     * @var RouterInterface
     */
    protected $router;


    /**
     * WebhookBuilder constructor.
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
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


        $customDomain = trim((string)getenv(self::CUSTOM_DOMAIN_ENV_KEY));

        if ($customDomain !== '') {

            $components = parse_url($webhookUrl);

            # replace old domain with new custom domain
            $webhookUrl = str_replace((string)$components['host'], $customDomain, $webhookUrl);
        }

        return $webhookUrl;
    }

}
