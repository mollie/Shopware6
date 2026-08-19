<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Mollie\Gateway\SessionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionBuilder;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Entry point Mollie redirects to once the shopper completed the payment inside the
 * express component.
 *
 * At that point there is no order yet, only a cart, and a cart cannot be looked up by the
 * Mollie session id: the session lives in the cart payload, which is stored as a blob. The
 * cart token is therefore part of the redirect url. It also tells a later step whether the
 * checkout started from a cart or from an existing order.
 */
#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
final class FinishCheckoutRoute extends AbstractFinishCheckoutRoute
{
    public const CART_TOKEN_PARAMETER = 'cartToken';

    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: SessionGateway::class)]
        private SessionGatewayInterface $sessionGateway,
        #[Autowire(service: SalesChannelContextService::class)]
        private SalesChannelContextServiceInterface $salesChannelContextService,
        private CartService $cartService,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function getDecorated(): AbstractFinishCheckoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'store-api.mollie.express-components.checkout.finish', path: '/store-api/mollie/express-components/finish', methods: ['GET', 'POST'])]
    public function finishCheckout(Request $request, SalesChannelContext $salesChannelContext): FinishCheckoutResponse
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        $settings = $this->settingsService->getExpressComponentsSettings($salesChannelId);
        if ($settings->isEnabled() === false) {
            throw ExpressComponentsException::notEnabled($salesChannelId);
        }

        $cartToken = (string) $request->get(self::CART_TOKEN_PARAMETER, '');
        if ($cartToken === '') {
            throw ExpressComponentsException::cartTokenIsEmpty();
        }

        $logData = [
            'cartToken' => $cartToken,
            'salesChannelId' => $salesChannelId,
        ];
        $this->logger->info('Start - finish express components checkout', $logData);

        // Mollie redirects the shopper back without the cart he started from, so the context
        // of that cart is restored from the token that was handed to the redirect url
        $cartContext = $this->restoreContext($cartToken, $salesChannelContext);
        $cart = $this->cartService->getCart($cartToken, $cartContext);

        $storedSession = $cart->getExtension(SessionBuilder::CART_EXTENSION);
        if (! $storedSession instanceof Session) {
            throw ExpressComponentsException::cartSessionIdIsEmpty($cartToken);
        }

        $session = $this->sessionGateway->getSession($storedSession->getId(), $cartContext);

        $logData['sessionId'] = $session->getId();
        $this->logger->info('Finished - finish express components checkout', $logData);

        return new FinishCheckoutResponse($session->getId(), $cartContext->getToken());
    }

    private function restoreContext(string $cartToken, SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        $customer = $salesChannelContext->getCustomer();

        return $this->salesChannelContextService->get(new SalesChannelContextServiceParameters(
            $salesChannelContext->getSalesChannelId(),
            $cartToken,
            originalContext: $salesChannelContext->getContext(),
            customerId: $customer?->getId(),
        ));
    }
}
