<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class TestModeNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AccountOverviewPageLoadedEvent::class => 'addTestModeToPage',
            AccountEditOrderPageLoadedEvent::class => 'addTestModeToPage',
            CheckoutConfirmPageLoadedEvent::class => 'addTestModeToPage',
            CheckoutFinishPageLoadedEvent::class => 'addTestModeToPage',
        ];
    }

    public function addTestModeToPage(PageLoadedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
        $isTestMode = $this->settingsService->getApiSettings($salesChannelId)->isTestMode();
        $event->getPage()->assign(['mollie_test_mode' => $isTestMode]);
    }
}
