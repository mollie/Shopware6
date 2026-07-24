<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Router;

use Mollie\Shopware\Component\Mollie\Payment;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

final class RouteBuilder implements RouteBuilderInterface
{
    public function __construct(
        #[Autowire(service: 'router')]
        private RouterInterface $router,
        #[Autowire(service: 'request_stack')]
        private RequestStack $requestStack,
        #[Autowire(value: '%env(default::APP_URL)%')]
        private string $appUrl = '')
    {
    }

    public function getReturnUrl(string $transactionId): string
    {
        $routeName = 'frontend.mollie.payment';

        if ($this->isStoreApiRequest()) {
            $routeName = 'api.mollie.payment-return';
        }

        $url = $this->router->generate($routeName, ['transactionId' => $transactionId], RouterInterface::ABSOLUTE_URL);

        return $this->normalizeUrl($url);
    }

    public function getWebhookUrl(string $transactionId): string
    {
        $routeName = 'frontend.mollie.webhook';
        if ($this->isStoreApiRequest()) {
            $routeName = 'api.mollie.webhook';
        }

        $url = $this->router->generate($routeName, ['transactionId' => $transactionId], RouterInterface::ABSOLUTE_URL);

        return $this->normalizeUrl($url);
    }

    public function getSubscriptionWebhookUrl(string $subscriptionId): string
    {
        $routeName = 'frontend.mollie.webhook.subscription';
        if ($this->isStoreApiRequest()) {
            $routeName = 'api.mollie.webhook.subscription';
        }

        $url = $this->router->generate($routeName, ['subscriptionId' => $subscriptionId], RouterInterface::ABSOLUTE_URL);

        return $this->normalizeUrl($url);
    }

    public function getSubscriptionPaymentUpdateReturnUrl(string $subscriptionId): string
    {
        return $this->router->generate(
            'frontend.account.mollie.subscriptions.payment.update-success',
            ['subscriptionId' => $subscriptionId],
            RouterInterface::ABSOLUTE_URL
        );
    }

    public function getSubscriptionPaymentUpdateWebhookUrl(string $subscriptionId): string
    {
        $url = $this->router->generate(
            'api.mollie.webhook.subscription.mandate.update',
            ['subscriptionId' => $subscriptionId],
            RouterInterface::ABSOLUTE_URL
        );

        return $this->normalizeUrl($url);
    }

    public function getPaypalExpressRedirectUrl(): string
    {
        $routeName = 'frontend.mollie.paypal-express.finish';
        if ($this->isStoreApiRequest()) {
            $routeName = 'store-api.mollie.paypal-express.checkout.finish';
        }

        return $this->router->generate($routeName, [], RouterInterface::ABSOLUTE_URL);
    }

    public function getPaypalExpressCancelUrl(): string
    {
        $routeName = 'frontend.mollie.paypal-express.cancel';
        if ($this->isStoreApiRequest()) {
            $routeName = 'store-api.mollie.paypal-express.checkout.cancel';
        }

        return $this->router->generate($routeName, [], RouterInterface::ABSOLUTE_URL);
    }

    public function getPosCheckoutUrl(Payment $payment,string $transactionId, string $orderNumber): string
    {
        $parameters = [
            'transactionId' => $transactionId,
            'orderNumber' => $orderNumber,
            'paymentId' => $payment->getId(),
        ];
        $changePaymentStatusUrl = $payment->getChangePaymentStateUrl();
        if (mb_strlen($changePaymentStatusUrl) > 0) {
            $parameters['changePaymentStateUrl'] = $changePaymentStatusUrl;
        }

        return $this->router->generate('frontend.mollie.pos.checkout', $parameters, RouterInterface::ABSOLUTE_URL);
    }

    private function isStoreApiRequest(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return false;
        }

        return str_starts_with($request->getPathInfo(), '/store-api');
    }

    /**
     * In a headless setup the store-api request originates from the storefront proxy (e.g. a Nuxt/Nitro
     * server), so the router builds absolute URLs against the proxy host instead of Shopware's public
     * domain. The resulting webhook and return URLs then point to a host Mollie cannot reach. For store-api
     * requests we therefore rebuild the origin (scheme/host/port) from Shopware's configured APP_URL, which
     * is the actual public domain of the backend. This only applies to the api.mollie.* routes served by
     * Shopware itself; storefront routes and their sales channel domains are left untouched. It is a no-op
     * for the classic (non-headless) checkout and when APP_URL is unset or points at localhost.
     */
    private function normalizeUrl(string $url): string
    {
        if (! $this->isStoreApiRequest()) {
            return $url;
        }

        $appHost = parse_url($this->appUrl, PHP_URL_HOST);
        if (! is_string($appHost) || $appHost === '' || $appHost === 'localhost') {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['path'])) {
            return $url;
        }

        $appScheme = parse_url($this->appUrl, PHP_URL_SCHEME);
        $appPort = parse_url($this->appUrl, PHP_URL_PORT);

        $origin = (is_string($appScheme) && $appScheme !== '' ? $appScheme : 'https') . '://' . $appHost;
        if (is_int($appPort)) {
            $origin .= ':' . $appPort;
        }

        $normalized = $origin . $parts['path'];
        if (isset($parts['query'])) {
            $normalized .= '?' . $parts['query'];
        }
        if (isset($parts['fragment'])) {
            $normalized .= '#' . $parts['fragment'];
        }

        return $normalized;
    }
}
