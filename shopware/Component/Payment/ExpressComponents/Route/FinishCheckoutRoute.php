<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Payment\ExpressComponents\CartCheckoutFinisher;
use Mollie\Shopware\Component\Payment\ExpressComponents\CartCheckoutFinisherInterface;
use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\OrderCheckoutFinisher;
use Mollie\Shopware\Component\Payment\ExpressComponents\OrderCheckoutFinisherInterface;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
 */
#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
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
        private OrderCheckoutFinisherInterface $orderCheckoutFinisher
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
}
