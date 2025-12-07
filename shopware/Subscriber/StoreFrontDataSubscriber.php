<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Component\Mollie\Locale;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Mandate\AbstractListMandatesRoute;
use Mollie\Shopware\Component\Payment\Mandate\ListMandatesRoute;
use Mollie\Shopware\Component\Payment\PointOfSale\AbstractListTerminalsRoute;
use Mollie\Shopware\Component\Payment\PointOfSale\ListTerminalsRoute;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
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
        #[Autowire(service: ListMandatesRoute::class)]
        private AbstractListMandatesRoute $listMandatesRoute,
        #[Autowire(service: ListTerminalsRoute::class)]
        private AbstractListTerminalsRoute $listTerminalsRoute,
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
        /** @var ?PaymentMethodExtension $mollieExtension */
        $mollieExtension = $selectedPaymentMethod->getExtension(Mollie::EXTENSION);

        if ($mollieExtension === null) {
            return;
        }
        /** @var Page $page */
        $page = $event->getPage();
        try {
            $apiSettings = $this->settings->getApiSettings($salesChannelContext->getSalesChannelId());

            $languageInfo = $salesChannelContext->getLanguageInfo();
            /** @phpstan-ignore-next-line */
            if ($languageInfo instanceof LanguageInfo) {
                $this->addMollieLocale($page, $languageInfo);
            }

            $this->addTestMode($page, $apiSettings);
            $this->addProfileId($page, $apiSettings);
            $this->addCreditCardSettings($page, $mollieExtension, $salesChannelContext);
            $this->addPosTerminals($page, $mollieExtension, $salesChannelContext);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to assign custom template data to pages', [
                'error' => $exception->getMessage(),
                'salesChannelId' => $salesChannelId,
            ]);
        }
    }

    private function addCreditCardSettings(Page $page, PaymentMethodExtension $paymentMethod, SalesChannelContext $salesChannelContext): void
    {
        if ($paymentMethod->getPaymentMethod() !== PaymentMethod::CREDIT_CARD) {
            return;
        }
        $listMandatesResponse = $this->listMandatesRoute->list('', $salesChannelContext);
        $mandates = $listMandatesResponse->getMandates();
        $creditCardMandates = $mandates->filterByPaymentMethod(PaymentMethod::CREDIT_CARD);

        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $creditCardSettings = $this->settings->getCreditCardSettings($salesChannelId);

        $page->addExtension('MollieCreditCardMandateCollection', $creditCardMandates);

        $page->assign([
            'enable_credit_card_components' => $creditCardSettings->isCreditCardComponentsEnabled(),
            'enable_one_click_payments' => $creditCardSettings->isOneClickPayment(),
            'enable_one_click_payments_compact_view' => $creditCardSettings->isOneClickCompactView()
        ]);
    }

    private function addPosTerminals(Page $page, PaymentMethodExtension $paymentMethod, SalesChannelContext $salesChannelContext): void
    {
        if ($paymentMethod->getPaymentMethod() !== PaymentMethod::POS) {
            return;
        }
        $listTerminalsResponse = $this->listTerminalsRoute->list($salesChannelContext);
        $terminals = $listTerminalsResponse->getTerminals();
        $page->assign([
            'mollie_terminals' => $terminals
        ]);
    }

    private function addProfileId(Page $page, ApiSettings $apiSettings): void
    {
        $page->assign([
            'mollie_profile_id' => $apiSettings->getProfileId()
        ]);
    }

    private function addTestMode(Page $page, ApiSettings $apiSettings): void
    {
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
