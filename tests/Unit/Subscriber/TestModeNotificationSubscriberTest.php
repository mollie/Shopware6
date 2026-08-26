<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscriber;

use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Mollie\PaymentMethod as MolliePaymentMethod;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Entity\PaymentMethod\PaymentMethod as PaymentMethodExtension;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Subscriber\TestModeNotificationSubscriber;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Fake\FakeShopwareTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPage;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(TestModeNotificationSubscriber::class)]
final class TestModeNotificationSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        $events = TestModeNotificationSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(AccountOverviewPageLoadedEvent::class, $events);
        $this->assertArrayHasKey(AccountEditOrderPageLoadedEvent::class, $events);
        $this->assertArrayHasKey(CheckoutConfirmPageLoadedEvent::class, $events);
        $this->assertArrayHasKey(CheckoutFinishPageLoadedEvent::class, $events);
    }

    /**
     * The template shows the test mode banner off this flag, so it has to be set on every page the
     * subscriber runs on - also when test mode is off, or the banner would linger.
     */
    public function testThePageLearnsWhetherTheShopIsInTestMode(): void
    {
        $page = new AccountOverviewPage();

        $this->subscriber(testMode: true)->addTestModeToPage(new AccountOverviewPageLoadedEvent($page, new FakeSalesChannelContext(), new Request()));

        $this->assertTrue($page->getVars()['mollie_test_mode']);
    }

    public function testALiveShopIsMarkedAsNotInTestMode(): void
    {
        $page = new AccountOverviewPage();

        $this->subscriber(testMode: false)->addTestModeToPage(new AccountOverviewPageLoadedEvent($page, new FakeSalesChannelContext(), new Request()));

        $this->assertFalse($page->getVars()['mollie_test_mode']);
    }

    /**
     * A shopper on the confirm page must see which payment methods will not take real money.
     */
    public function testAMollieMethodIsMarkedAsTestModeInTheCheckout(): void
    {
        $page = $this->confirmPageWith($this->molliePaymentMethod('Credit card'));

        $this->subscriber(testMode: true)->addTestModeToPage(new CheckoutConfirmPageLoadedEvent($page, new FakeSalesChannelContext(), new Request()));

        $this->assertSame('Credit card (molliePayments.testMode.label)', $page->getPaymentMethods()->first()?->getTranslated()['name']);
    }

    public function testAMethodOfAnotherProviderKeepsItsName(): void
    {
        $foreign = new PaymentMethodEntity();
        $foreign->setId('payment-method-2');
        $foreign->setName('Invoice');
        $foreign->setTranslated(['name' => 'Invoice']);

        $page = $this->confirmPageWith($foreign);

        $this->subscriber(testMode: true)->addTestModeToPage(new CheckoutConfirmPageLoadedEvent($page, new FakeSalesChannelContext(), new Request()));

        $this->assertSame('Invoice', $page->getPaymentMethods()->first()?->getTranslated()['name']);
    }

    public function testNoMethodIsRelabelledOnALiveShop(): void
    {
        $page = $this->confirmPageWith($this->molliePaymentMethod('Credit card'));

        $this->subscriber(testMode: false)->addTestModeToPage(new CheckoutConfirmPageLoadedEvent($page, new FakeSalesChannelContext(), new Request()));

        $this->assertSame('Credit card', $page->getPaymentMethods()->first()?->getTranslated()['name']);
    }

    /**
     * The finish page has no payment method list to relabel - only the banner flag applies there.
     */
    public function testAPageWithoutPaymentMethodsOnlyGetsTheFlag(): void
    {
        $page = new AccountOverviewPage();

        $this->subscriber(testMode: true)->addTestModeToPage(new AccountOverviewPageLoadedEvent($page, new FakeSalesChannelContext(), new Request()));

        $this->assertTrue($page->getVars()['mollie_test_mode']);
    }

    /**
     * The label is a snippet, so a shop in another language does not get an English suffix.
     */
    public function testTheLabelComesFromTheSnippetFiles(): void
    {
        $translator = new FakeShopwareTranslator();
        $page = $this->confirmPageWith($this->molliePaymentMethod('Kreditkarte'));

        $this->subscriber(testMode: true, translator: $translator)
            ->addTestModeToPage(new CheckoutConfirmPageLoadedEvent($page, new FakeSalesChannelContext(), new Request()))
        ;

        $this->assertSame(['molliePayments.testMode.label'], $translator->getRequestedSnippets());
    }

    private function molliePaymentMethod(string $name): PaymentMethodEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('payment-method-1');
        $paymentMethod->setName($name);
        $paymentMethod->setTranslated(['name' => $name]);
        $paymentMethod->addExtension(Mollie::EXTENSION, new PaymentMethodExtension('payment-method-1', MolliePaymentMethod::CREDIT_CARD));

        return $paymentMethod;
    }

    private function confirmPageWith(PaymentMethodEntity $paymentMethod): CheckoutConfirmPage
    {
        $page = new CheckoutConfirmPage();
        $page->setPaymentMethods(new PaymentMethodCollection([$paymentMethod]));

        return $page;
    }

    private function subscriber(bool $testMode, ?FakeShopwareTranslator $translator = null): TestModeNotificationSubscriber
    {
        $apiSettings = new ApiSettings('test_key', 'live_key', $testMode ? Mode::TEST : Mode::LIVE, 'pfl_1');

        return new TestModeNotificationSubscriber(
            new FakeSettingsService(apiSettings: $apiSettings),
            $translator ?? new FakeShopwareTranslator()
        );
    }
}
