<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaypalExpressCheckoutSubscriber implements EventSubscriberInterface
{
    public const MOLLIE_PAYPAL_EXPRESS_CHECKOUT_BUTTON_DATA_EXTENSION_ID = 'molliePayPalEcsButtonData';

    /** @var SettingsService */
    private $settingsService;

    public function __construct(
        SettingsService $settingsService
    )
    {
        $this->settingsService = $settingsService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutCartPageLoadedEvent::class => 'addExpressCheckoutDataToPage',
            CheckoutRegisterPageLoadedEvent::class => 'addExpressCheckoutDataToPage',
            NavigationPageLoadedEvent::class => 'addExpressCheckoutDataToPage',
            OffcanvasCartPageLoadedEvent::class => 'addExpressCheckoutDataToPage',
            ProductPageLoadedEvent::class => 'addExpressCheckoutDataToPage',

            CmsPageLoadedEvent::class => 'addExpressCheckoutDataToCmsPage',
        ];
    }

    public function addExpressCheckoutDataToPage(PageLoadedEvent $event): void
    {
        $event->getPage()->addExtension(
            self::MOLLIE_PAYPAL_EXPRESS_CHECKOUT_BUTTON_DATA_EXTENSION_ID,
            $this->paypalSettings($event->getSalesChannelContext())
        );
    }

    public function addExpressCheckoutDataToCmsPage(CmsPageLoadedEvent $event): void
    {
        /** @var CmsPageCollection $pages */
        $pages = $event->getResult();

        $cmsPage = $pages->first();

        if ($cmsPage === null) {
            return;
        }

        $cmsPage->addExtension(
            self::MOLLIE_PAYPAL_EXPRESS_CHECKOUT_BUTTON_DATA_EXTENSION_ID,
            $this->paypalSettings($event->getSalesChannelContext())
        );
    }

    private function paypalSettings(SalesChannelContext $context): ArrayStruct
    {
        $settings = $this->settingsService->getSettings($context->getSalesChannel()->getId(), $context->getContext());

        return new ArrayStruct([
            'enabled' => $settings->isEnablePayPalShortcut(),
            'color' => $settings->getPaypalShortcutButtonColor(),
            'label' => $settings->getPaypalShortcutButtonLabel(),
        ]);
    }
}
