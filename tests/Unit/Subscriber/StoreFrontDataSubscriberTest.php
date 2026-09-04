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
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Mollie\Shopware\Entity\Product\Product;
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
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
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

    protected function setUp(): void
    {
        $this->logger = new FakeLogger();
    }

    public function testSubscribedEvents(): void
    {
        $events = StoreFrontDataSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(CheckoutConfirmPageLoadedEvent::class, $events);
        $this->assertArrayHasKey(AccountEditOrderPageLoadedEvent::class, $events);
    }

    public function testNoDataIsAssignedForAPaymentMethodThatIsNotFromMollie(): void
    {
        $subscriber = $this->createSubscriber();
        $page = $this->confirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, new PaymentMethodEntity()));

        self::assertArrayNotHasKey('mollie_locale', $page->getVars());
    }

    public function testMollieLocaleIsAssignedFromTheSalesChannelLanguage(): void
    {
        $subscriber = $this->createSubscriber(languageRepository: new FakeLanguageRepository('de-DE'));
        $page = $this->confirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::IDEAL)));

        self::assertSame('de_DE', $page->getVars()['mollie_locale']);
    }

    public function testMollieLocaleFallsBackToEnglishWithoutALanguage(): void
    {
        $subscriber = $this->createSubscriber();
        $page = $this->confirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::IDEAL)));

        self::assertSame('en_GB', $page->getVars()['mollie_locale']);
    }

    public function testConfiguredProfileIdIsAssignedWithoutAskingMollie(): void
    {
        $gateway = new FakeGateway();
        $subscriber = $this->createSubscriber(
            settings: new FakeSettingsService(apiSettings: new ApiSettings('test_key', 'live_key', Mode::TEST, 'pfl_configured')),
            gateway: $gateway,
        );
        $page = $this->confirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::IDEAL)));

        self::assertSame('pfl_configured', $page->getVars()['mollie_profile_id']);
        self::assertSame(0, $gateway->getCallCount('getCurrentProfile'));
    }

    public function testProfileIdIsReadFromMollieAndStoredWhenItIsNotConfigured(): void
    {
        $settings = new FakeSettingsService(apiSettings: new ApiSettings('test_key', 'live_key', Mode::TEST, ''));
        $subscriber = $this->createSubscriber(settings: $settings);
        $page = $this->confirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::IDEAL)));

        self::assertSame('fake_profile', $page->getVars()['mollie_profile_id']);
        self::assertSame('fake_profile', $settings->getApiSettings()->getProfileId());
    }

    public function testOnlyCreditCardMandatesAreAssignedToThePage(): void
    {
        $mandates = new MandateCollection([
            new Mandate('mdt_creditcard', PaymentMethod::CREDIT_CARD, []),
            new Mandate('mdt_paypal', PaymentMethod::PAYPAL, []),
        ]);
        $subscriber = $this->createSubscriber(
            settings: $this->settingsWithStoredCardsEnabled(),
            mandatesRoute: new FakeListMandatesRoute($mandates),
        );
        $page = $this->confirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD)));

        $assigned = $page->getExtension('MollieCreditCardMandateCollection');
        self::assertInstanceOf(MandateCollection::class, $assigned);
        self::assertCount(1, $assigned);
        self::assertSame('mdt_creditcard', $assigned->first()?->getId());
    }

    /**
     * The card form is what a stored card is picked in, so without it the mandates cannot be shown -
     * and asking Mollie for them costs an API call for something nobody sees.
     */
    public function testMandatesAreNotLoadedWhenTheCreditCardComponentsAreDisabled(): void
    {
        $mandatesRoute = new FakeListMandatesRoute(new MandateCollection([
            new Mandate('mdt_creditcard', PaymentMethod::CREDIT_CARD, []),
        ]));
        $subscriber = $this->createSubscriber(
            settings: new FakeSettingsService(
                paymentSettings: new PaymentSettings('', 0, oneClickPayment: true),
                creditCardSettings: new CreditCardSettings(false),
            ),
            mandatesRoute: $mandatesRoute,
        );
        $page = $this->confirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD)));

        self::assertSame(0, $mandatesRoute->getCallCount());
        self::assertCount(0, $page->getExtension('MollieCreditCardMandateCollection'));
    }

    public function testMandatesAreNotLoadedWhenOneClickPaymentsAreDisabled(): void
    {
        $mandatesRoute = new FakeListMandatesRoute(new MandateCollection([
            new Mandate('mdt_creditcard', PaymentMethod::CREDIT_CARD, []),
        ]));
        $subscriber = $this->createSubscriber(
            settings: new FakeSettingsService(
                paymentSettings: new PaymentSettings('', 0, oneClickPayment: false),
                creditCardSettings: new CreditCardSettings(true),
            ),
            mandatesRoute: $mandatesRoute,
        );
        $page = $this->confirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD)));

        self::assertSame(0, $mandatesRoute->getCallCount());
        self::assertCount(0, $page->getExtension('MollieCreditCardMandateCollection'));
    }

    public function testCreditCardComponentAndOneClickSettingsAreAssignedToThePage(): void
    {
        $subscriber = $this->createSubscriber(
            settings: new FakeSettingsService(
                paymentSettings: new PaymentSettings('', 0, oneClickPayment: true, oneClickCompactView: true),
                creditCardSettings: new CreditCardSettings(true),
            ),
        );
        $page = $this->confirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD)));

        $vars = $page->getVars();
        self::assertTrue($vars['enable_credit_card_components']);
        self::assertTrue($vars['enable_one_click_payments']);
        self::assertTrue($vars['enable_one_click_payments_compact_view']);
    }

    public function testTheSaveCardCheckboxIsShownForARegisteredCustomerWithoutASubscription(): void
    {
        $subscriber = $this->createSubscriber(settings: $this->settingsWithStoredCardsEnabled());
        $page = $this->confirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD), $this->registeredCustomer()));

        self::assertTrue($page->getVars()['mollie_show_save_card_checkbox']);
    }

    /**
     * The mandate a subscription needs comes from its own first payment, and PayloadBuilder drops
     * the field for subscription orders - so the checkbox would promise something that is ignored.
     */
    public function testTheSaveCardCheckboxIsHiddenForASubscriptionCart(): void
    {
        $subscriber = $this->createSubscriber(settings: $this->settingsWithStoredCardsEnabled());
        $page = $this->confirmPage($this->subscriptionLineItems());

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD), $this->registeredCustomer()));

        self::assertFalse($page->getVars()['mollie_show_save_card_checkbox']);
    }

    public function testTheSaveCardCheckboxIsHiddenForAGuest(): void
    {
        $guest = $this->registeredCustomer();
        $guest->setGuest(true);

        $subscriber = $this->createSubscriber(settings: $this->settingsWithStoredCardsEnabled());
        $page = $this->confirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD), $guest));

        self::assertFalse($page->getVars()['mollie_show_save_card_checkbox']);
    }

    public function testTheSaveCardCheckboxIsHiddenWhenOneClickPaymentsAreDisabled(): void
    {
        $subscriber = $this->createSubscriber(
            settings: new FakeSettingsService(
                paymentSettings: new PaymentSettings('', 0, oneClickPayment: false),
                creditCardSettings: new CreditCardSettings(true),
            ),
        );
        $page = $this->confirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD), $this->registeredCustomer()));

        self::assertFalse($page->getVars()['mollie_show_save_card_checkbox']);
    }

    public function testTheSaveCardCheckboxIsHiddenWhenTheCreditCardComponentsAreDisabled(): void
    {
        $subscriber = $this->createSubscriber(
            settings: new FakeSettingsService(
                paymentSettings: new PaymentSettings('', 0, oneClickPayment: true),
                creditCardSettings: new CreditCardSettings(false),
            ),
        );
        $page = $this->confirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD), $this->registeredCustomer()));

        self::assertFalse($page->getVars()['mollie_show_save_card_checkbox']);
    }

    public function testMandatesAreNotLoadedForAnotherPaymentMethod(): void
    {
        $mandatesRoute = new FakeListMandatesRoute();
        $subscriber = $this->createSubscriber(mandatesRoute: $mandatesRoute);

        $subscriber->addDataToPage($this->confirmEvent($this->confirmPage(), $this->molliePaymentMethod(PaymentMethod::IDEAL)));

        self::assertSame(0, $mandatesRoute->getCallCount());
    }

    public function testTerminalsAreAssignedForPointOfSale(): void
    {
        $terminals = new TerminalCollection([
            new Terminal('term_123', 'Checkout counter', 'EUR', TerminalStatus::ACTIVE, TerminalBrand::PAX, TerminalModel::A920),
        ]);
        $subscriber = $this->createSubscriber(terminalsRoute: new FakeListTerminalsRoute($terminals));
        $page = $this->confirmPage();

        $subscriber->addDataToPage($this->confirmEvent($page, $this->molliePaymentMethod(PaymentMethod::POS)));

        self::assertSame($terminals, $page->getVars()['mollie_terminals']);
    }

    public function testTerminalsAreNotLoadedForAnotherPaymentMethod(): void
    {
        $terminalsRoute = new FakeListTerminalsRoute();
        $subscriber = $this->createSubscriber(terminalsRoute: $terminalsRoute);

        $subscriber->addDataToPage($this->confirmEvent($this->confirmPage(), $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD)));

        self::assertSame(0, $terminalsRoute->getCallCount());
    }

    public function testAFailingMandateLookupIsLoggedInsteadOfBreakingTheCheckout(): void
    {
        $subscriber = $this->createSubscriber(
            settings: $this->settingsWithStoredCardsEnabled(),
            mandatesRoute: new FakeListMandatesRoute(new MandateCollection(), new \RuntimeException('Mollie API not reachable')),
        );

        $subscriber->addDataToPage($this->confirmEvent($this->confirmPage(), $this->molliePaymentMethod(PaymentMethod::CREDIT_CARD)));

        self::assertTrue($this->logger->hasRecordThatContains('error', 'Failed to assign custom template data to pages'));
    }

    public function testDataIsAlsoAssignedWhenAnExistingOrderIsEdited(): void
    {
        $subscriber = $this->createSubscriber(languageRepository: new FakeLanguageRepository('nl-NL'));
        $page = new AccountEditOrderPage();

        $salesChannelContext = new FakeSalesChannelContext();
        $salesChannelContext->setPaymentMethod($this->molliePaymentMethod(PaymentMethod::IDEAL));

        $subscriber->addDataToPage(new AccountEditOrderPageLoadedEvent($page, $salesChannelContext, new Request()));

        self::assertSame('nl_NL', $page->getVars()['mollie_locale']);
    }

    /**
     * Both switches on, the only combination in which a stored card can be selected.
     */
    private function settingsWithStoredCardsEnabled(): FakeSettingsService
    {
        return new FakeSettingsService(
            paymentSettings: new PaymentSettings('', 0, oneClickPayment: true),
            creditCardSettings: new CreditCardSettings(true),
        );
    }

    private function createSubscriber(
        ?FakeSettingsService $settings = null,
        ?FakeListMandatesRoute $mandatesRoute = null,
        ?FakeListTerminalsRoute $terminalsRoute = null,
        ?FakeGateway $gateway = null,
        ?FakeLanguageRepository $languageRepository = null,
    ): StoreFrontDataSubscriber {
        return new StoreFrontDataSubscriber(
            $settings ?? new FakeSettingsService(),
            $mandatesRoute ?? new FakeListMandatesRoute(),
            $terminalsRoute ?? new FakeListTerminalsRoute(),
            $gateway ?? new FakeGateway(),
            new LocaleProvider($languageRepository ?? new FakeLanguageRepository()),
            new LineItemAnalyzer(),
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

    /**
     * The subscriber reads the cart line items, which the real page loader always sets.
     */
    private function confirmPage(?LineItemCollection $lineItems = null): CheckoutConfirmPage
    {
        $cart = new Cart('cart-token');
        if ($lineItems !== null) {
            $cart->setLineItems($lineItems);
        }

        $page = new CheckoutConfirmPage();
        $page->setCart($cart);

        return $page;
    }

    private function subscriptionLineItems(): LineItemCollection
    {
        $subscriptionProduct = new Product();
        $subscriptionProduct->setIsSubscription(true);

        $lineItem = new LineItem('line-item-id', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->addExtension(Mollie::EXTENSION, $subscriptionProduct);

        return new LineItemCollection([$lineItem]);
    }

    private function confirmEvent(CheckoutConfirmPage $page, PaymentMethodEntity $paymentMethod, ?CustomerEntity $customer = null): CheckoutConfirmPageLoadedEvent
    {
        $salesChannelContext = new FakeSalesChannelContext();
        $salesChannelContext->setPaymentMethod($paymentMethod);
        $salesChannelContext->setCustomer($customer);

        return new CheckoutConfirmPageLoadedEvent($page, $salesChannelContext, new Request());
    }

    private function registeredCustomer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId('customer-id');
        $customer->setGuest(false);

        return $customer;
    }
}
