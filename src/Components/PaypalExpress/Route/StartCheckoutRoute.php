<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\PaypalExpress\Route;

use Kiener\MolliePayments\Components\PaypalExpress\PayPalExpress;
use Kiener\MolliePayments\Components\PaypalExpress\PaypalExpressException;
use Kiener\MolliePayments\Service\CartServiceInterface;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Shopware\Entity\Cart\MollieShopwareCart;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @final
 */
class StartCheckoutRoute extends AbstractStartCheckoutRoute
{
    private SettingsService $settingsService;
    private CartServiceInterface $cartService;
    private PayPalExpress $paypalExpress;

    public function __construct(
        SettingsService $settingsService,
        CartServiceInterface $cartService,
        PayPalExpress $paypalExpress
    ) {
        $this->settingsService = $settingsService;
        $this->cartService = $cartService;
        $this->paypalExpress = $paypalExpress;
    }

    public function getDecorated(): AbstractStartCheckoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws PaymentException
     */
    public function startCheckout(Request $request, SalesChannelContext $context): StartCheckoutResponse
    {
        $settings = $this->settingsService->getSettings($context->getSalesChannelId());

        if ($settings->isPaypalExpressEnabled() === false) {
            throw PaypalExpressException::paymentNotEnabled($context->getSalesChannelId());
        }

        $cart = $this->cartService->getCalculatedMainCart($context);

        if ($cart->getLineItems()->count() === 0) {
            throw PaypalExpressException::cartIsEmpty();
        }

        $mollieShopwareCart = new MollieShopwareCart($cart);

        $sessionId = $mollieShopwareCart->getPayPalExpressSessionID();

        if ($sessionId === '') {
            $session = $this->paypalExpress->startSession($cart, $context);
        } else {
            $session = $this->paypalExpress->loadSession($sessionId, $context);
        }

        if ($session->id === null) {
            throw PaypalExpressException::missingSessionId();
        }

        $mollieShopwareCart->setPayPalExpressSessionID($session->id);

        if ($settings->isRequireDataProtectionCheckbox()) {
            $mollieShopwareCart->setDataProtectionAccepted((int) $request->get(CustomFieldsInterface::ACCEPTED_DATA_PROTECTION, 0));
        }

        $cart = $mollieShopwareCart->getCart();

        $this->cartService->persistCart($cart, $context);

        return new StartCheckoutResponse(
            $session->id,
            $session->getRedirectUrl()
        );
    }
}
