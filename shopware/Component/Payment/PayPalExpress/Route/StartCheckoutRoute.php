<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PayPalExpress\Route;

use Mollie\Shopware\Component\Mollie\Gateway\SessionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Payment\PayPalExpress\PaypalExpressException;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
final class StartCheckoutRoute extends AbstractStartCheckoutRoute
{
    public const REDIRECT_URL_PARAMETER = 'redirectUrl';
    public const CANCEL_URL_PARAMETER = 'cancelUrl';

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

        $redirectUrl = trim((string) $request->get(self::REDIRECT_URL_PARAMETER, ''));
        $cancelUrl = trim((string) $request->get(self::CANCEL_URL_PARAMETER, ''));

        $session = $this->sessionGateway->createPaypalExpressSession($cart, $salesChannelContext, $redirectUrl, $cancelUrl);

        // the guest account is created in the finish route after returning from PayPal,
        // where the storefront checkbox no longer exists - so keep the consent on the session
        $session->setAcceptedDataProtection((int) $request->get('acceptedDataProtection', '0') === 1);

        $cart->addExtension(Mollie::EXTENSION, $session);

        $this->cartService->recalculate($cart, $salesChannelContext);

        return new StartCheckoutResponse(
            $session->getId(),
            $session->getRedirectUrl(),
        );
    }
}
