<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPage;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class TestModeNotificationSubscriber implements EventSubscriberInterface
{
    private const TEST_MODE_SNIPPET = 'molliePayments.testMode.label';

    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        #[Autowire(service: Translator::class)]
        private AbstractTranslator $translator,
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

        $page = $event->getPage();
        $page->assign(['mollie_test_mode' => $isTestMode]);

        if ($isTestMode === false) {
            return;
        }

        if ($page instanceof CheckoutConfirmPage === false && $page instanceof AccountEditOrderPage === false) {
            return;
        }

        $this->appendTestModeLabelToMolliePaymentMethods($page->getPaymentMethods());
    }

    private function appendTestModeLabelToMolliePaymentMethods(PaymentMethodCollection $paymentMethods): void
    {
        $label = $this->translator->trans(self::TEST_MODE_SNIPPET);

        foreach ($paymentMethods as $paymentMethod) {
            if ($paymentMethod->hasExtension(Mollie::EXTENSION) === false) {
                continue;
            }

            $translated = $paymentMethod->getTranslated();
            $name = $translated['name'] ?? $paymentMethod->getName() ?? '';
            $translated['name'] = sprintf('%s (%s)', $name, $label);
            $paymentMethod->setTranslated($translated);
        }
    }
}
