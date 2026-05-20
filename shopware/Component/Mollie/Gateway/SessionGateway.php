<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Exception\ClientException;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Router\RouteBuilder;
use Mollie\Shopware\Component\Router\RouteBuilderInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SessionGateway implements SessionGatewayInterface
{
    use ExceptionTrait;

    private const SESSION_MAX_RETRY = 5;
    private const SESSION_BASE_TIMEOUT = 500_000;

    public function __construct(
        #[Autowire(service: ClientFactory::class)]
        private ClientFactoryInterface $clientFactory,
        #[Autowire(service: RouteBuilder::class)]
        private RouteBuilderInterface $routeBuilder,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function createPaypalExpressSession(Cart $cart, SalesChannelContext $salesChannelContext): Session
    {
        try {
            $salesChannelId = $salesChannelContext->getSalesChannelId();
            $currencyIso = $salesChannelContext->getCurrency()->getIsoCode();
            $client = $this->clientFactory->create($salesChannelId);
            $amount = new Money($cart->getPrice()->getTotalPrice(), $currencyIso);
            $method = PaymentMethod::PAYPAL;
            $formParams = [
                'method' => $method->value,
                'methodDetails' => [
                    'checkoutFlow' => 'express',
                ],
                'amount' => $amount->toArray(),
                'redirectUrl' => $this->routeBuilder->getPaypalExpressRedirectUrl(),
                'cancelUrl' => $this->routeBuilder->getPaypalExpressCancelUrl(),
            ];

            $response = $client->post('sessions', [
                'form_params' => $formParams,
            ]);
            $body = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Paypal express session created', [
                'requestParameter' => $formParams,
                'responseParameter' => $body,
                'salesChannelId' => $salesChannelId,
            ]);

            $session = Session::createFromClientResponse($body);
            $session->setAuthenticationId('');

            return $session;
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }

    public function loadSession(string $sessionId, SalesChannelContext $salesChannelContext): Session
    {
        try {
            $salesChannelId = $salesChannelContext->getSalesChannelId();
            $client = $this->clientFactory->create($salesChannelId);

            $response = $client->get('sessions/' . $sessionId);
            $session = Session::createFromClientResponse(json_decode($response->getBody()->getContents(), true));

            for ($i = 0; $i < self::SESSION_MAX_RETRY && $session->getShippingAddress() === null; ++$i) {
                usleep(self::SESSION_BASE_TIMEOUT * ($i + 1));
                $response = $client->get('sessions/' . $sessionId);
                $session = Session::createFromClientResponse(json_decode($response->getBody()->getContents(), true));
            }

            $this->logger->info('Session loaded', [
                'sessionId' => $sessionId,
                'salesChannelId' => $salesChannelId,
            ]);

            return $session;
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }

    public function cancelSession(string $sessionId, SalesChannelContext $salesChannelContext): Session
    {
        try {
            $salesChannelId = $salesChannelContext->getSalesChannelId();

            $client = $this->clientFactory->create($salesChannelId);
            $response = $client->delete('sessions/' . $sessionId);
            $body = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Session cancelled', [
                'sessionId' => $sessionId,
                'responseParameter' => $body,
                'salesChannelId' => $salesChannelId,
            ]);

            return Session::createFromClientResponse($body);
        } catch (ClientException $exception) {
            throw $this->convertException($exception);
        }
    }
}
