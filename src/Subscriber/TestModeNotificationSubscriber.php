<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Storefront\Struct\TestModePageExtensionStruct;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TestModeNotificationSubscriber implements EventSubscriberInterface
{
    /** @var SettingsService */
    private $settingsService;

    /**
     * Creates a new instance of PaymentMethodSubscriber.
     */
    public function __construct(
        SettingsService $settingsService
    ) {
        $this->settingsService = $settingsService;
    }

    public static function getSubscribedEvents()
    {
        return [
            AccountOverviewPageLoadedEvent::class => 'addTestModeInformationToPages',
            /** @phpstan-ignore class.notFound */
            AccountPaymentMethodPageLoadedEvent::class => 'addTestModeInformationToPages',
            AccountEditOrderPageLoadedEvent::class => 'addTestModeInformationToPages',
            CheckoutConfirmPageLoadedEvent::class => 'addTestModeInformationToPages',
            CheckoutFinishPageLoadedEvent::class => 'addTestModeInformationToPages',
        ];
    }

    public function addTestModeInformationToPages(PageLoadedEvent $event): void
    {
        $settings = $this->settingsService->getSettings($event->getSalesChannelContext()->getSalesChannel()->getId());
        $event->getPage()->addExtension('MollieTestModePageExtension', new TestModePageExtensionStruct($settings->isTestMode()));
    }
}
