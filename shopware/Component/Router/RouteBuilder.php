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
        #[Autowire(service: 'router.default')]
        private RouterInterface $router,
        #[Autowire(service: 'request_stack')]
        private RequestStack $requestStack)
    {
    }

    public function getReturnUrl(string $transactionId): string
    {
        $routeName = 'frontend.mollie.payment';

        if ($this->isStoreApiRequest()) {
            $routeName = 'api.mollie.payment-return';
        }

        return $this->router->generate($routeName, ['transactionId' => $transactionId], RouterInterface::ABSOLUTE_URL);
    }

    public function getWebhookUrl(string $transactionId): string
    {
        $routeName = 'frontend.mollie.webhook';
        if ($this->isStoreApiRequest()) {
            $routeName = 'api.mollie.webhook';
        }

        return $this->router->generate($routeName, ['transactionId' => $transactionId], RouterInterface::ABSOLUTE_URL);
    }

    public function getPaypalExpressRedirectUrl(): string
    {
        $routeName = 'frontend.mollie.paypal-express.finish';
        if ($this->isStoreApiRequest()) {
            $routeName = 'astore-api.mollie.paypal-express.checkout.finish';
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
            'orderNumber' => $orderNumber
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
}
