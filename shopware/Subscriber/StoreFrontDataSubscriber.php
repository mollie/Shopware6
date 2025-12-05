<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Component\Mollie\Gateway\ProfileGateway;
use Mollie\Shopware\Component\Mollie\Gateway\ProfileGatewayInterface;
use Mollie\Shopware\Component\Mollie\Locale;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class StoreFrontDataSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settings,
        #[Autowire(service: ProfileGateway::class)]
        private ProfileGatewayInterface $profileGateway,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => ['addDataToPage', 10],
            AccountEditOrderPageLoadedEvent::class => ['addDataToPage', 10],
        ];
    }

    public function addDataToPage(PageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $selectedPaymentMethod = $salesChannelContext->getPaymentMethod();
        $mollieExtension = $selectedPaymentMethod->getExtension(Mollie::EXTENSION);

        if ($mollieExtension === null) {
            return;
        }
        /** @var Page $page */
        $page = $event->getPage();
        try {
            $languageInfo = $salesChannelContext->getLanguageInfo();
            /** @phpstan-ignore-next-line */
            if ($languageInfo instanceof LanguageInfo) {
                $this->addMollieLocale($page, $languageInfo);
            }
            $this->addTestMode($page, $salesChannelContext);
            $this->addProfileId($page, $salesChannelContext);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to assign custom template data to pages', [
                'error' => $exception->getMessage(),
                'salesChannelId' => $salesChannelId,
            ]);
        }
    }

    private function addProfileId(Page $page, SalesChannelContext $salesChannelContext): void
    {
        $profile = $this->profileGateway->getCurrentProfile($salesChannelContext->getSalesChannelId());
        $page->assign([
            'mollie_profile_id' => $profile->getId()
        ]);
    }

    private function addTestMode(Page $page, SalesChannelContext $salesChannelContext): void
    {
        $apiSettings = $this->settings->getApiSettings($salesChannelContext->getSalesChannelId());
        $page->assign([
            'mollie_test_mode' => $apiSettings->isTestMode() ? 'true' : 'false',
        ]);
    }

    private function addMollieLocale(Page $page, LanguageInfo $languageInfo): void
    {
        $page->assign([
            'mollie_locale' => Locale::fromLocaleCode($languageInfo->localeCode)
        ]);
    }
}
