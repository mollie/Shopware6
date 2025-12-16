<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Components\PaypalExpress\PayPalExpress;
use Kiener\MolliePayments\Handler\Method\PayPalExpressPayment;
use Kiener\MolliePayments\Service\CartServiceInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Traits\StringTrait;
use Mollie\Shopware\Component\Payment\ExpressMethod\CartBackupService;
use Mollie\Shopware\Entity\Cart\MollieShopwareCart;
use Mollie\Shopware\Entity\Order\MollieShopwareOrder;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class PaypalExpressSubscriber implements EventSubscriberInterface
{
    use StringTrait;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var PayPalExpress
     */
    private $paypal;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var CartServiceInterface
     */
    private $mollieCartService;

    /**
     * @var CartBackupService
     */
    private $cartBackupService;

    public function __construct(SettingsService $settingsService, PayPalExpress $paypal, CartBackupService $cartBackupService, CartService $cartService, CartServiceInterface $mollieCartService)
    {
        $this->settingsService = $settingsService;
        $this->paypal = $paypal;
        $this->cartBackupService = $cartBackupService;
        $this->cartService = $cartService;
        $this->mollieCartService = $mollieCartService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
            CheckoutFinishPageLoadedEvent::class => 'onCheckoutSuccess',
            ResponseEvent::class => 'onResetPaypalExpress',
        ];
    }

    /**
     * @throws \Exception
     */
    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $settings = $this->settingsService->getSettings($event->getSalesChannelContext()->getSalesChannel()->getId());

        $paymentEnabled = $this->isPPEActive($event->getSalesChannelContext());

        $event->setParameter('mollie_paypalexpress_enabled', $paymentEnabled);

        $style = $settings->getPaypalExpressButtonStyle();
        $shape = $settings->getPaypalExpressButtonShape();

        $restrictions = $settings->getPaypalExpressRestrictions();

        $event->setParameter('mollie_paypalexpress_style', $style);
        $event->setParameter('mollie_paypalexpress_shape', $shape);
        $event->setParameter('mollie_paypalexpress_restrictions', $restrictions);
    }

    /**
     * If our apple pay direct payment is done, we want to restore the original cart
     * just in case if the customer had some items in there.
     */
    public function onCheckoutSuccess(CheckoutFinishPageLoadedEvent $event): void
    {
        $mollieShopwareOrder = new MollieShopwareOrder($event->getPage()->getOrder());

        $latestTransaction = $mollieShopwareOrder->getLatestTransaction();

        if (! $latestTransaction instanceof OrderTransactionEntity) {
            return;
        }

        $paymentMethod = $latestTransaction->getPaymentMethod();

        if (! $paymentMethod instanceof PaymentMethodEntity) {
            return;
        }

        $paymentIdentifier = $paymentMethod->getHandlerIdentifier();

        if ($paymentIdentifier !== PayPalExpressPayment::class) {
            return;
        }

        $context = $event->getSalesChannelContext();

        if ($this->cartBackupService->isBackupExisting($context)) {
            $this->cartBackupService->clearBackup($context);
        }
    }

    /**
     * If the user is on the PayPal page during the express checkout
     * but somehow gets back to the shop outside of our success/cancel controller actions
     * we need to check if we need to cancel PPE. otherwise the user would be stuck in that process.
     */
    public function onResetPaypalExpress(ResponseEvent $event): void
    {
        $salesChannelContext = $event->getRequest()->attributes->get('sw-sales-channel-context');

        if (! $salesChannelContext instanceof SalesChannelContext) {
            return;
        }

        $pathInfo = $event->getRequest()->getPathInfo();

        // we must not clear things in our controlled PayPal express process
        if ($this->stringContains($pathInfo, '/mollie/paypal-express')) {
            return;
        }

        $paymentEnabled = $this->isPPEActive($salesChannelContext);

        // now we need to figure out if the user came back from PayPal express before finalizing the authentication.
        // If so, we need to reset PayPal express, otherwise the user would be stuck in using it
        if (! $paymentEnabled) {
            return;
        }

        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $mollieShopwareCart = new MollieShopwareCart($cart);

        // if paypal is either not started or confirmed, then do nothing
        // incomplete means, we have started it, but authorization was not finished
        if (! $mollieShopwareCart->isPayPalExpressIncomplete()) {
            return;
        }

        if ($mollieShopwareCart->isSingleProductExpressCheckout()) {
            // we want to restore our original cart
            $cart = $this->cartBackupService->restoreCart($salesChannelContext);
            $this->cartBackupService->clearBackup($salesChannelContext);

            $mollieShopwareCart = new MollieShopwareCart($cart);
        }

        // always make sure that paypal express data is really cleaned
        $mollieShopwareCart->clearPayPalExpress();
        $cart = $mollieShopwareCart->getCart();

        $this->mollieCartService->persistCart($cart, $salesChannelContext);
    }

    private function isPPEActive(SalesChannelContext $context): bool
    {
        $settings = $this->settingsService->getSettings($context->getSalesChannel()->getId());

        if (! $settings->isPaypalExpressEnabled()) {
            return false;
        }

        return $this->paypal->isPaypalExpressPaymentMethodEnabled($context);
    }
}
