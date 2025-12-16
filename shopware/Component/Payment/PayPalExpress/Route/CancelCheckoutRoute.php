<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PayPalExpress\Route;

use Mollie\Shopware\Component\Mollie\Gateway\SessionGateway;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Payment\Method\PayPalExpressPayment;
use Mollie\Shopware\Component\Payment\PayPalExpress\PaypalExpressException;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Repository\PaymentMethodRepository;
use Mollie\Shopware\Repository\PaymentMethodRepositoryInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(defaults: ['_routeScope' => ['store-api']])]
final class CancelCheckoutRoute extends AbstractCancelCheckoutRoute
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: PaymentMethodRepository::class)]
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        #[Autowire(service: SessionGateway::class)]
        private SessionGatewayInterface $sessionGateway,
        private CartService $cartService,
    ) {
    }

    public function getDecorated(): AbstractCancelCheckoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(name: 'store-api.mollie.paypal-express.checkout.cancel', path: '/store-api/mollie/paypal-express/cancel', methods: ['GET'])]
    public function cancel(SalesChannelContext $salesChannelContext): CancelCheckoutResponse
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
        $cart->removeExtension(Mollie::EXTENSION);
        $this->cartService->recalculate($cart, $salesChannelContext);

        try {
            $this->sessionGateway->cancelSession($cartExtension->getId(), $salesChannelContext);
        } catch (\Throwable $exception) {
        }

        return new CancelCheckoutResponse($cartExtension->getId());
    }
}
