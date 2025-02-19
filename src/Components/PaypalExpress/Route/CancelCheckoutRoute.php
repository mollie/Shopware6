<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\PaypalExpress\Route;

use Kiener\MolliePayments\Components\PaypalExpress\PayPalExpress;
use Kiener\MolliePayments\Components\PaypalExpress\PaypalExpressException;
use Kiener\MolliePayments\Service\CartServiceInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Shopware\Entity\Cart\MollieShopwareCart;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @final
 */
class CancelCheckoutRoute extends AbstractCancelCheckoutRoute
{
    private SettingsService $settingsService;
    private PayPalExpress $paypalExpress;
    private CartServiceInterface $cartService;

    public function __construct(
        SettingsService      $settingsService,
        CartServiceInterface $cartService,
        PayPalExpress        $paypalExpress
    ) {
        $this->settingsService = $settingsService;
        $this->paypalExpress = $paypalExpress;
        $this->cartService = $cartService;
    }

    public function getDecorated(): AbstractStartCheckoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function cancelCheckout(SalesChannelContext $context): CancelCheckoutResponse
    {
        $settings = $this->settingsService->getSettings($context->getSalesChannelId());

        if ($settings->isPaypalExpressEnabled() === false) {
            throw PaypalExpressException::paymentNotEnabled($context->getSalesChannelId());
        }

        $cart = $this->cartService->getCalculatedMainCart($context);
        $mollieShopwareCart = new MollieShopwareCart($cart);

        # get the existing session ID
        $sessionId = $mollieShopwareCart->getPayPalExpressSessionID();

        # clear the Auth-ID and save the cart
        # this is important, the rest of the handling is done in the PaypalExpressSubscriber
        $mollieShopwareCart->setPayPalExpressAuthenticateId('');
        $cart = $mollieShopwareCart->getCart();
        $this->cartService->persistCart($cart, $context);

        if ($sessionId === '') {
            throw PaypalExpressException::cartSessionIdIsEmpty();
        }

        try {
            $this->paypalExpress->cancelSession($sessionId, $context);
        } catch (\Throwable $e) {
            //todo: remove try catch once cancel is possible from mollie
        }
        return new CancelCheckoutResponse($sessionId);
    }
}
