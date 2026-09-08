<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\Subscriber;

use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsData;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\AbstractCreateSessionRoute;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\CreateSessionRoute;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestriction;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Shopware\Storefront\Page\Page;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

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
        #[Autowire(service: CreateSessionRoute::class)]
        private AbstractCreateSessionRoute $createSessionRoute,
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
        $page = $event->getPage();

        $this->assignData($page, VisibilityRestriction::CART, $event->getSalesChannelContext(), $page->getCart());
    }

    public function onOffcanvasCartPageLoaded(OffcanvasCartPageLoadedEvent $event): void
    {
        $page = $event->getPage();

        $this->assignData($page, VisibilityRestriction::OFF_CANVAS, $event->getSalesChannelContext(), $page->getCart());
    }

    public function onConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $page = $event->getPage();

        $this->assignData($page, VisibilityRestriction::CONFIRM, $event->getSalesChannelContext(), $page->getCart());
    }

    /**
     * A failed payment sends the customer to the edit order page, where the order already exists
     * and there is no cart. The session is built from the order instead.
     */
    public function onEditOrderPageLoaded(AccountEditOrderPageLoadedEvent $event): void
    {
        $page = $event->getPage();

        $this->assignData($page, VisibilityRestriction::CONFIRM, $event->getSalesChannelContext(), null, $page->getOrder());
    }

    private function assignData(Page $page, VisibilityRestriction $position, SalesChannelContext $salesChannelContext, ?Cart $cart = null, ?OrderEntity $order = null): void
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        try {
            $settings = $this->settings->getExpressComponentsSettings($salesChannelId);
            if ($settings->isEnabled() === false) {
                return;
            }

            $restrictions = $settings->getRestrictions();
            $page->addExtension(ExpressComponentsData::EXTENSION, new ExpressComponentsData(true, $restrictions->toArray()));

            if ($restrictions->contains($position)) {
                return;
            }

            $request = new Request();
            $response = $this->createSessionRoute->createSession($request, $salesChannelContext, $cart, $order);

            $page->addExtension(ExpressComponentsData::EXTENSION, new ExpressComponentsData(
                true,
                $restrictions->toArray(),
                $response->getSessionId(),
                $response->getClientAccessToken()
            ));
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to assign express components data to storefront', [
                'error' => $exception->getMessage(),
                'salesChannelId' => $salesChannelId,
            ]);
        }
    }
}
