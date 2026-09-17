<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Payment\ExpressComponents\CartCheckoutFinisher;
use Mollie\Shopware\Component\Payment\ExpressComponents\CartCheckoutFinisherInterface;
use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\FinishUrls;
use Mollie\Shopware\Component\Payment\ExpressComponents\OrderCheckoutFinisher;
use Mollie\Shopware\Component\Payment\ExpressComponents\OrderCheckoutFinisherInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Entry point Mollie redirects to once the shopper completed the payment inside the
 * express component.
 *
 * At that point there is usually no order yet, only a cart, and a cart cannot be looked up by the
 * Mollie session id: the session lives in the cart payload, which is stored as a blob. The
 * cart token is therefore part of the redirect url. It also tells this route whether the
 * checkout started from a cart or from an existing order, which are two different flows.
 *
 * Mollie redirects a browser, and a browser cannot send the sw-access-key header the store-api
 * demands, so the route lives in the api scope with authentication disabled - like the payment
 * return and the webhooks - and reads the sales channel out of its path. The storefront reaches
 * the same checkout through finishCheckout(), where the session already carries the context.
 */
#[AsController]
#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => false, 'auth_enabled' => false])]
final class FinishCheckoutRoute extends AbstractFinishCheckoutRoute
{
    public const CART_TOKEN_PARAMETER = 'cartToken';
    public const ORDER_ID_PARAMETER = 'orderId';

    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: CartCheckoutFinisher::class)]
        private CartCheckoutFinisherInterface $cartCheckoutFinisher,
        #[Autowire(service: OrderCheckoutFinisher::class)]
        private OrderCheckoutFinisherInterface $orderCheckoutFinisher,
        #[Autowire(service: SalesChannelContextService::class)]
        private SalesChannelContextServiceInterface $salesChannelContextService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function getDecorated(): AbstractFinishCheckoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'api.mollie.express-components.finish', path: '/api/mollie/express-components/finish/{salesChannelId}', methods: ['GET', 'POST'])]
    public function finish(string $salesChannelId, Request $request): RedirectResponse
    {
        $finishUrls = FinishUrls::fromRequest($request);
        $cartToken = (string) $request->get(self::CART_TOKEN_PARAMETER, '');
        $orderId = (string) $request->get(self::ORDER_ID_PARAMETER, '');

        try {
            $salesChannelContext = $this->buildContext($salesChannelId, $cartToken);
            $response = $this->finishCheckout($request, $salesChannelContext);

            $redirectUrl = $response->getRedirectUrl();
            if ($redirectUrl !== '') {
                return new RedirectResponse($redirectUrl);
            }

            return new RedirectResponse($finishUrls->getFinishUrl($response->getOrderId()));
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to finish express components checkout', [
                'error' => $exception->getMessage(),
                'cartToken' => $cartToken,
                'orderId' => $orderId,
                'salesChannelId' => $salesChannelId,
            ]);

            $errorUrl = $finishUrls->getErrorUrl($orderId);
            if ($errorUrl === '') {
                throw $exception;
            }

            return new RedirectResponse($errorUrl);
        }
    }

    public function finishCheckout(Request $request, SalesChannelContext $salesChannelContext): FinishCheckoutResponse
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $settings = $this->settingsService->getExpressComponentsSettings($salesChannelId);
        if ($settings->isEnabled() === false) {
            throw ExpressComponentsException::notEnabled($salesChannelId);
        }

        // the edit order page has no cart, there the order takes the place of the cart token
        $orderId = (string) $request->get(self::ORDER_ID_PARAMETER, '');
        if ($orderId !== '') {
            return $this->orderCheckoutFinisher->finish($orderId, $salesChannelContext);
        }

        $cartToken = (string) $request->get(self::CART_TOKEN_PARAMETER, '');
        if ($cartToken === '') {
            throw ExpressComponentsException::cartTokenIsEmpty();
        }

        return $this->cartCheckoutFinisher->finish($cartToken, $salesChannelContext);
    }

    private function buildContext(string $salesChannelId, string $cartToken): SalesChannelContext
    {
        $token = $cartToken !== '' ? $cartToken : Uuid::randomHex();
        $parameters = new SalesChannelContextServiceParameters($salesChannelId, $token);

        return $this->salesChannelContextService->get($parameters);
    }
}
