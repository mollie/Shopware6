<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PayPalExpress;

use Kiener\MolliePayments\Struct\Address\AddressStruct;
use Mollie\Shopware\Component\Account\AbstractAccountService;
use Mollie\Shopware\Component\Account\AccountService;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Entity\Cart\MollieShopwareCart;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class FinishCheckoutRoute extends AbstractFinishCheckoutRoute
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: SessionGateway::class)]
        private SessionGatewayInterface $sessionGateway,
        #[Autowire(service: AccountService::class)]
        private AbstractAccountService $accountService,
        private CartService $cartService,
    ) {
    }

    public function getDecorated(): AbstractStartCheckoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'store-api.mollie.paypal-express.checkout.finish', path: '/store-api/mollie/paypal-express/finish', methods: ['GET'])]
    public function finishCheckout(SalesChannelContext $salesChannelContext): FinishCheckoutResponse
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $settings = $this->settingsService->getPaypalExpressSettings($salesChannelId);

        if ($settings->isEnabled() === false) {
            throw PaypalExpressException::paymentNotEnabled($salesChannelId);
        }

        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
        /** @var ?Session $cartExtension */
        $cartExtension = $cart->getExtension(Mollie::EXTENSION);
        if ($cartExtension === null) {
            throw PaypalExpressException::cartSessionIdIsEmpty();
        }
        $session = $this->sessionGateway->loadSession($cartExtension->getId(), $salesChannelContext);

        $billingAddress = $session->getBillingAddress();
        if ($billingAddress === null) {
            throw PaypalExpressException::billingAddressMissing();
        }
        $shippingAddress = $session->getShippingAddress();
        if ($shippingAddress === null) {
            throw PaypalExpressException::shippingAddressMissing();
        }

        $newContext = $this->accountService->loginOrCreateAccount($billingAddress,$shippingAddress,$salesChannelContext);
        $cart = $this->cartService->getCart($newContext->getToken(), $newContext);
        $cart->addExtension(Mollie::EXTENSION, $session);
        $this->cartService->recalculate($cart,$salesChannelContext);

        return new FinishCheckoutResponse($session->getId(), $session->getAuthenticationId());

        $settings = $this->settingsService->getSettings($context->getSalesChannelId());

        if ($settings->isPaypalExpressEnabled() === false) {
            throw PaypalExpressException::paymentNotEnabled($context->getSalesChannelId());
        }

        $cart = $this->cartService->getCalculatedMainCart($context);
        $mollieShopwareCart = new MollieShopwareCart($cart);

        $payPalExpressSessionId = $mollieShopwareCart->getPayPalExpressSessionID();
        $acceptedDataProtection = $mollieShopwareCart->isDataProtectionAccepted();

        if ($payPalExpressSessionId === '') {
            throw PaypalExpressException::cartSessionIdIsEmpty();
        }

        $payPalExpressSession = $this->paypalExpress->loadSession($payPalExpressSessionId, $context);

        $methodDetails = $payPalExpressSession->methodDetails;

        if (! property_exists($methodDetails, 'shippingAddress') || $methodDetails->shippingAddress === null) {
            throw PaypalExpressException::shippingAddressMissing();
        }
        if (! property_exists($methodDetails, 'billingAddress') || $methodDetails->billingAddress === null) {
            throw PaypalExpressException::billingAddressMissing();
        }

        $billingAddress = null;

        $mollieShippingAddress = $methodDetails->shippingAddress;
        if (! property_exists($mollieShippingAddress, 'phone')) {
            $mollieShippingAddress->phone = '';
        }
        if (! property_exists($mollieShippingAddress, 'streetAdditional')) {
            $mollieShippingAddress->streetAdditional = '';
        }
        if (! property_exists($mollieShippingAddress, 'email')) {
            $mollieShippingAddress->email = '';
        }

        if ($methodDetails->billingAddress->streetAdditional !== null) {
            $mollieShippingAddress->streetAdditional = $methodDetails->billingAddress->streetAdditional;
        }
        if ($methodDetails->billingAddress->phone !== null) {
            $mollieShippingAddress->phone = $methodDetails->billingAddress->phone;
        }
        if ($methodDetails->billingAddress->email !== null) {
            $mollieShippingAddress->email = $methodDetails->billingAddress->email;
        }
        if ($methodDetails->billingAddress->streetAndNumber !== null) {
            try {
                $billingAddress = AddressStruct::createFromApiResponse($methodDetails->billingAddress);
            } catch (\Throwable $e) {
                throw PaypalExpressException::billingAddressError($e->getMessage(), $methodDetails->billingAddress);
            }
        }

        try {
            $shippingAddress = AddressStruct::createFromApiResponse($mollieShippingAddress);
        } catch (\Throwable $e) {
            throw PaypalExpressException::shippingAddressError($e->getMessage(), $mollieShippingAddress);
        }
        $oldToken = $context->getToken();
        // create new account or find existing and login
        $context = $this->paypalExpress->prepareCustomer($shippingAddress, $context, $acceptedDataProtection, $billingAddress);

        // read a new card after login
        if ($context->getToken() !== $oldToken) {
            $cart = $this->cartService->getCalculatedMainCart($context);
        }

        // we have to update the cart extension before a new user is created and logged in, otherwise the extension is not saved
        $mollieShopwareCart = new MollieShopwareCart($cart);
        $mollieShopwareCart->setPayPalExpressAuthenticateId($payPalExpressSession->authenticationId);

        $cart = $mollieShopwareCart->getCart();
        $this->cartService->updateCart($cart);
        $this->cartService->persistCart($cart, $context);

        return new FinishCheckoutResponse($payPalExpressSession->id, $payPalExpressSession->authenticationId);
    }
}
