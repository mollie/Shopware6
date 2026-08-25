<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscriber;

use Mollie\Shopware\Component\Mollie\Mandate;
use Mollie\Shopware\Component\Mollie\MandateCollection;
use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\Terminal;
use Mollie\Shopware\Component\Mollie\TerminalBrand;
use Mollie\Shopware\Component\Mollie\TerminalCollection;
use Mollie\Shopware\Component\Mollie\TerminalModel;
use Mollie\Shopware\Component\Mollie\TerminalStatus;
use Mollie\Shopware\Component\SalesChannel\LocaleProvider;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Settings\Struct\CreditCardSettings;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Subscriber\StoreFrontDataSubscriber;
use Mollie\Shopware\Unit\Fake\FakeLanguageRepository;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Payment\Mandate\Fake\FakeListMandatesRoute;
use Mollie\Shopware\Unit\Payment\PointOfSale\Fake\FakeListTerminalsRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPage;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(StoreFrontDataSubscriber::class)]
final class StoreFrontDataSubscriberTest extends TestCase
{
    private FakeLogger $logger;

    public function testSubscribedEvents(): void
    {
        $events = StoreFrontDataSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(CheckoutConfirmPageLoadedEvent::class, $events);
        $this->assertArrayHasKey(AccountEditOrderPageLoadedEvent::class, $events);
    }

    public function testNoDataIsAssignedForAPaymentMethodThatIsNotFromMollie(): void
    {
        $subscriber = $this->createSubscriber();
        $page = new CheckoutConfirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, new PaymentMethodEntity()));

        static::assertArrayNotHasKey('mollie_locale', $page->getVars());
    }

    public function testMollieLocaleIsAssignedFromTheSalesChannelLanguage(): void
    {
        $subscriber = $this->createSubscriber(languageRepository: new FakeLanguageRepository('de-DE'));
        $page = new CheckoutConfirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::IDEAL)));

        static::assertSame('de_DE', $page->getVars()['mollie_locale']);
    }

    public function testMollieLocaleFallsBackToEnglishWithoutALanguage(): void
    {
        $subscriber = $this->createSubscriber();
        $page = new CheckoutConfirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::IDEAL)));

        static::assertSame('en_GB', $page->getVars()['mollie_locale']);
    }

    public function testConfiguredProfileIdIsAssignedWithoutAskingMollie(): void
    {
        $gateway = new FakeGateway();
        $subscriber = $this->createSubscriber(
            settings: new FakeSettingsService(apiSettings: new ApiSettings('test_key', 'live_key', Mode::TEST, 'pfl_configured')),
            gateway: $gateway,
        );
        $page = new CheckoutConfirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::IDEAL)));

        static::assertSame('pfl_configured', $page->getVars()['mollie_profile_id']);
        static::assertSame(0, $gateway->getCallCount('getCurrentProfile'));
    }

    public function testProfileIdIsReadFromMollieAndStoredWhenItIsNotConfigured(): void
    {
        $settings = new FakeSettingsService(apiSettings: new ApiSettings('test_key', 'live_key', Mode::TEST, ''));
        $subscriber = $this->createSubscriber(settings: $settings);
        $page = new CheckoutConfirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::IDEAL)));

        static::assertSame('fake_profile', $page->getVars()['mollie_profile_id']);
        static::assertSame('fake_profile', $settings->getApiSettings()->getProfileId());
    }

    public function testOnlyCreditCardMandatesAreAssignedToThePage(): void
    {
        $mandates = new MandateCollection([
            new Mandate('mdt_creditcard', PaymentMethod::CREDIT_CARD, []),
            new Mandate('mdt_paypal', PaymentMethod::PAYPAL, []),
        ]);
        $subscriber = $this->createSubscriber(mandatesRoute: new FakeListMandatesRoute($mandates));
        $page = new CheckoutConfirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD)));

        $assigned = $page->getExtension('MollieCreditCardMandateCollection');
        static::assertInstanceOf(MandateCollection::class, $assigned);
        static::assertCount(1, $assigned);
        static::assertSame('mdt_creditcard', $assigned->first()?->getId());
    }

    public function testCreditCardComponentAndOneClickSettingsAreAssignedToThePage(): void
    {
        $subscriber = $this->createSubscriber(
            settings: new FakeSettingsService(
                paymentSettings: new PaymentSettings('', 0, true, true),
                creditCardSettings: new CreditCardSettings(true),
            ),
        );
        $page = new CheckoutConfirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD)));

        $vars = $page->getVars();
        static::assertTrue($vars['enable_credit_card_components']);
        static::assertTrue($vars['enable_one_click_payments']);
        static::assertTrue($vars['enable_one_click_payments_compact_view']);
    }

    public function testMandatesAreNotLoadedForAnotherPaymentMethod(): void
    {
        $mandatesRoute = new FakeListMandatesRoute();
        $subscriber = $this->createSubscriber(mandatesRoute: $mandatesRoute);

        $subscriber->addDataToPage($this->confirmEvent(new CheckoutConfirmPage(), $this->molliePaymentMethod(PaymentMethod::IDEAL)));

        static::assertSame(0, $mandatesRoute->getCallCount());
    }

    public function testTerminalsAreAssignedForPointOfSale(): void
    {
        $terminals = new TerminalCollection([
            new Terminal('term_123', 'Checkout counter', 'EUR', TerminalStatus::ACTIVE, TerminalBrand::PAX, TerminalModel::A920),
        ]);
        $subscriber = $this->createSubscriber(terminalsRoute: new FakeListTerminalsRoute($terminals));
        $page = new CheckoutConfirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::POS)));

        static::assertSame($terminals, $page->getVars()['mollie_terminals']);
    }

    public function testTerminalsAreNotLoadedForAnotherPaymentMethod(): void
    {
        $terminalsRoute = new FakeListTerminalsRoute();
        $subscriber = $this->createSubscriber(terminalsRoute: $terminalsRoute);

        $subscriber->addDataToPage($this->confirmEvent(new CheckoutConfirmPage(), $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD)));

        static::assertSame(0, $terminalsRoute->getCallCount());
    }

    public function testAFailingMandateLookupIsLoggedInsteadOfBreakingTheCheckout(): void
    {
        $subscriber = $this->createSubscriber(
            mandatesRoute: new FakeListMandatesRoute(new MandateCollection(), new \RuntimeException('Mollie API not reachable')),
        );

        $subscriber->addDataToPage($this->confirmEvent(new CheckoutConfirmPage(), $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD)));

        static::assertTrue($this->logger->hasRecordThatContains('error', 'Failed to assign custom template data to pages'));
    }

    public function testDataIsAlsoAssignedWhenAnExistingOrderIsEdited(): void
    {
        $subscriber = $this->createSubscriber(languageRepository: new FakeLanguageRepository('nl-NL'));
        $page = new AccountEditOrderPage();

        $salesChannelContext = new FakeSalesChannelContext();
        $salesChannelContext->setPaymentMethod($this->molliePaymentMethod(PaymentMethod::IDEAL));

        $subscriber->addDataToPage(new AccountEditOrderPageLoadedEvent($page, $salesChannelContext, new Request()));

        static::assertSame('nl_NL', $page->getVars()['mollie_locale']);
    }

    private function createSubscriber(
        ?FakeSettingsService $settings = null,
        ?FakeListMandatesRoute $mandatesRoute = null,
        ?FakeListTerminalsRoute $terminalsRoute = null,
        ?FakeGateway $gateway = null,
        ?FakeLanguageRepository $languageRepository = null,
    ): StoreFrontDataSubscriber {
        $this->logger = new FakeLogger();

        return new StoreFrontDataSubscriber(
            $settings ?? new FakeSettingsService(),
            $mandatesRoute ?? new FakeListMandatesRoute(),
            $terminalsRoute ?? new FakeListTerminalsRoute(),
            $gateway ?? new FakeGateway(),
            new LocaleProvider($languageRepository ?? new FakeLanguageRepository()),
            $this->logger,
        );
    }

    private function molliePaymentMethod(PaymentMethod $paymentMethod): PaymentMethodEntity
    {
        $entity = new PaymentMethodEntity();
        $entity->setId('payment-method-id');
        $entity->addExtension(Mollie::EXTENSION, new PaymentMethodExtension('payment-method-id', $paymentMethod));

        return $entity;
    }

    private function confirmEvent(CheckoutConfirmPage $page, PaymentMethodEntity $paymentMethod): CheckoutConfirmPageLoadedEvent
    {
        $salesChannelContext = new FakeSalesChannelContext();
        $salesChannelContext->setPaymentMethod($paymentMethod);

        return new CheckoutConfirmPageLoadedEvent($page, $salesChannelContext, new Request());
    }
}
