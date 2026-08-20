<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Subscriber;

use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsData;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionBuilder;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionBuilderInterface;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestriction;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Shopware\Storefront\Page\Page;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The amount of a session has to match the cart, and shipping costs and cart rules are only
 * known once a cart exists. The session is therefore created when a cart is rendered, not on
 * the product detail page.
 */
final class ExpressComponentsCartSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settings,
        #[Autowire(service: SessionBuilder::class)]
        private SessionBuilderInterface $sessionBuilder,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutCartPageLoadedEvent::class => 'onCartPageLoaded',
            OffcanvasCartPageLoadedEvent::class => 'onOffcanvasCartPageLoaded',
            CheckoutConfirmPageLoadedEvent::class => 'onConfirmPageLoaded',
            AccountEditOrderPageLoadedEvent::class => 'onEditOrderPageLoaded',
        ];
    }

    public function onCartPageLoaded(CheckoutCartPageLoadedEvent $event): void
    {
        $this->assignData($event->getPage(), $event->getPage()->getCart(), VisibilityRestriction::CART, $event->getSalesChannelContext());
    }

    public function onOffcanvasCartPageLoaded(OffcanvasCartPageLoadedEvent $event): void
    {
        $this->assignData($event->getPage(), $event->getPage()->getCart(), VisibilityRestriction::OFF_CANVAS, $event->getSalesChannelContext());
    }

    public function onConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $this->assignData($event->getPage(), $event->getPage()->getCart(), VisibilityRestriction::CONFIRM, $event->getSalesChannelContext());
    }

    /**
     * A failed payment sends the customer to the edit order page, where the order already exists
     * and there is no cart. The session is built from the order instead.
     */
    public function onEditOrderPageLoaded(AccountEditOrderPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        try {
            $settings = $this->settings->getExpressComponentsSettings($salesChannelId);
            if (! $settings->isEnabled()) {
                return;
            }

            $restrictions = $settings->getRestrictions()->toArray();
            $page->addExtension(ExpressComponentsData::EXTENSION, new ExpressComponentsData(true, $restrictions));

            if (in_array(VisibilityRestriction::CONFIRM->value, $restrictions, true)) {
                return;
            }

            $session = $this->sessionBuilder->buildFromOrder($page->getOrder(), $salesChannelContext);

            $page->addExtension(ExpressComponentsData::EXTENSION, new ExpressComponentsData(
                true,
                $restrictions,
                $session->getId(),
                $session->getClientAccessToken()
            ));
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to assign express components data to the edit order page', [
                'error' => $exception->getMessage(),
                'salesChannelId' => $salesChannelId,
            ]);
        }
    }

    private function assignData(Page $page, Cart $cart, VisibilityRestriction $restriction, SalesChannelContext $salesChannelContext): void
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        try {
            $settings = $this->settings->getExpressComponentsSettings($salesChannelId);
            if (! $settings->isEnabled()) {
                return;
            }

            $restrictions = $settings->getRestrictions()->toArray();
            $page->addExtension(ExpressComponentsData::EXTENSION, new ExpressComponentsData(true, $restrictions));

            if (in_array($restriction->value, $restrictions, true)) {
                return;
            }

            if ($cart->getLineItems()->count() === 0) {
                return;
            }

            $session = $this->sessionBuilder->buildFromCart($cart, $salesChannelContext);

            $page->addExtension(ExpressComponentsData::EXTENSION, new ExpressComponentsData(
                true,
                $restrictions,
                $session->getId(),
                $session->getClientAccessToken()
            ));
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to assign express components data to storefront', [
                'error' => $exception->getMessage(),
                'salesChannelId' => $salesChannelId,
            ]);
        }
    }
}
