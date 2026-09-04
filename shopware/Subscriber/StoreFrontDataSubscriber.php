<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGatewayInterface;
use Mollie\Shopware\Component\Mollie\Locale;
use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Mandate\Route\AbstractListMandatesRoute;
use Mollie\Shopware\Component\Payment\Mandate\Route\ListMandatesRoute;
use Mollie\Shopware\Component\Payment\PointOfSale\Route\AbstractListTerminalsRoute;
use Mollie\Shopware\Component\Payment\PointOfSale\Route\ListTerminalsRoute;
use Mollie\Shopware\Component\SalesChannel\LocaleProvider;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Settings\Struct\CreditCardSettings;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzerInterface;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Mollie\Shopware\Mollie;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPage;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
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
        #[Autowire(service: MollieGateway::class)]
        private MollieGatewayInterface $mollieGateway,
        private LocaleProvider $localeProvider,
        #[Autowire(service: LineItemAnalyzer::class)]
        private LineItemAnalyzerInterface $lineItemAnalyzer,
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
            $apiSettings = $this->settings->getApiSettings($salesChannelId);

            $localeCode = $this->localeProvider->getLocaleCode(
                $salesChannelContext->getLanguageId(),
                $salesChannelContext->getContext()
            );
            $this->addMollieLocale($page, $localeCode);

            $this->addProfileId($page, $apiSettings, $salesChannelId);
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
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $creditCardSettings = $this->settings->getCreditCardSettings($salesChannelId);
        $paymentSettings = $this->settings->getPaymentSettings($salesChannelId);

        $page->addExtension('MollieCreditCardMandateCollection', $this->findSelectableMandates($creditCardSettings, $paymentSettings, $salesChannelContext));

        $page->assign([
            'enable_credit_card_components' => $creditCardSettings->isCreditCardComponentsEnabled(),
            'enable_one_click_payments' => $paymentSettings->isOneClickPayment(),
            'enable_one_click_payments_compact_view' => $paymentSettings->isOneClickCompactView(),
            'mollie_show_save_card_checkbox' => $this->showSaveCardCheckbox($page, $creditCardSettings, $paymentSettings, $salesChannelContext),
        ]);
    }

    /**
     * A guest has no account to reuse the card from, and a subscription order gets its mandate from
     * the first payment either way - PayloadBuilder drops the field in both cases, so offering the
     * checkbox would promise something the payload ignores.
     */
    private function showSaveCardCheckbox(Page $page, CreditCardSettings $creditCardSettings, PaymentSettings $paymentSettings, SalesChannelContext $salesChannelContext): bool
    {
        if (! $creditCardSettings->isCreditCardComponentsEnabled()) {
            return false;
        }

        if (! $paymentSettings->isOneClickPayment()) {
            return false;
        }

        $customer = $salesChannelContext->getCustomer();
        if (! $customer instanceof CustomerEntity || $customer->getGuest()) {
            return false;
        }

        return ! $this->lineItemAnalyzer->hasSubscriptionProduct($this->resolveLineItems($page));
    }

    /**
     * @return LineItemCollection|OrderLineItemCollection
     */
    private function resolveLineItems(Page $page): Collection
    {
        if ($page instanceof CheckoutConfirmPage) {
            return $page->getCart()->getLineItems();
        }

        if ($page instanceof AccountEditOrderPage) {
            return $page->getOrder()->getLineItems() ?? new OrderLineItemCollection();
        }

        return new LineItemCollection();
    }

    /**
     * A stored card is picked inside the card form, so with either switch off there is nothing the
     * customer could select and no reason to ask Mollie for the mandates.
     */
    private function findSelectableMandates(CreditCardSettings $creditCardSettings, PaymentSettings $paymentSettings, SalesChannelContext $salesChannelContext): MandateCollection
    {
        if (! $creditCardSettings->isCreditCardComponentsEnabled()) {
            return new MandateCollection();
        }

        if (! $paymentSettings->isOneClickPayment()) {
            return new MandateCollection();
        }

        return $this->listMandatesRoute->list('', $salesChannelContext)
            ->getMandates()
            ->filterByPaymentMethod(PaymentMethod::CREDIT_CARD)
        ;
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

    private function addProfileId(Page $page, ApiSettings $apiSettings, string $salesChannelId): void
    {
        $profileId = $apiSettings->getProfileId();
        if ($profileId === '') {
            $profile = $this->mollieGateway->getCurrentProfile($salesChannelId);
            $profileId = $profile->getId();
            $apiSettings->setProfileId($profileId);
            $this->settings->setApiSettings($apiSettings, $salesChannelId);
        }

        $page->assign([
            'mollie_profile_id' => $profileId
        ]);
    }

    private function addMollieLocale(Page $page, string $localeCode): void
    {
        $page->assign([
            'mollie_locale' => Locale::fromLocaleCode($localeCode)->value
        ]);
    }
}
