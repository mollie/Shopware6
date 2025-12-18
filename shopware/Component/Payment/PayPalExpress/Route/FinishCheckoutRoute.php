<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PayPalExpress\Route;

use Mollie\Shopware\Component\Mollie\Gateway\SessionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Payment\ExpressMethod\AbstractAccountService;
use Mollie\Shopware\Component\Payment\ExpressMethod\AccountService;
use Mollie\Shopware\Component\Payment\Method\PayPalExpressPayment;
use Mollie\Shopware\Component\Payment\PaymentMethodRepository;
use Mollie\Shopware\Component\Payment\PaymentMethodRepositoryInterface;
use Mollie\Shopware\Component\Payment\PayPalExpress\PaypalExpressException;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
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
        #[Autowire(service: PaymentMethodRepository::class)]
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
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
        $payPalExpressId = $this->paymentMethodRepository->getIdByPaymentHandler(PayPalExpressPayment::class, $salesChannelId, $salesChannelContext->getContext());
        if ($payPalExpressId === null) {
            throw PaypalExpressException::paymentNotEnabled($salesChannelId);
        }

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

        $newContext = $this->accountService->loginOrCreateAccount($payPalExpressId, $billingAddress, $shippingAddress, $salesChannelContext);
        $cart = $this->cartService->getCart($newContext->getToken(), $newContext);
        $cart->addExtension(Mollie::EXTENSION, $session);
        $this->cartService->recalculate($cart, $salesChannelContext);

        return new FinishCheckoutResponse($session->getId(), $session->getAuthenticationId());
    }
}
