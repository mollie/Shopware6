<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PayPalExpress;

use Mollie\Shopware\Component\Mollie\Gateway\SessionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class StartCheckoutRoute extends AbstractStartCheckoutRoute
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: SessionGateway::class)]
        private SessionGatewayInterface $sessionGateway,
        private CartService $cartService,
    ) {
    }

    public function getDecorated(): AbstractStartCheckoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'store-api.mollie.paypal-express.checkout.start', path: '/store-api/mollie/paypal-express/start', methods: ['GET'])]
    public function startCheckout(Request $request, SalesChannelContext $salesChannelContext): StartCheckoutResponse
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $settings = $this->settingsService->getPaypalExpressSettings($salesChannelId);

        if ($settings->isEnabled() === false) {
            throw PaypalExpressException::paymentNotEnabled($salesChannelId);
        }

        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        if ($cart->getLineItems()->count() === 0) {
            throw PaypalExpressException::cartIsEmpty();
        }

        $session = $this->sessionGateway->createPaypalExpressSession($cart, $salesChannelContext);

        $cart->addExtension(Mollie::EXTENSION, $session);

        $this->cartService->recalculate($cart, $salesChannelContext);

        return new StartCheckoutResponse(
            $session->getId(),
            $session->getRedirectUrl(),
        );
    }
}
