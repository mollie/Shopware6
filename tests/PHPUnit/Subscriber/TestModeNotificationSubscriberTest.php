<?php declare(strict_types=1);

namespace MolliePayments\Tests\Subscriber;

use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Subscriber\TestModeNotificationSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPage;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPage;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPage;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPage;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class TestModeNotificationSubscriberTest extends TestCase
{
    private TestModeNotificationSubscriber $subscriber;
    private SettingsService $settingsServiceMock;

    public function setUp(): void
    {
        $this->settingsServiceMock = $this->createMock(SettingsService::class);
        $this->subscriber = new TestModeNotificationSubscriber($this->settingsServiceMock);
    }

    public function testAccountOverviewPageDoesContainTestModeInformation(): void
    {
        $event = new AccountOverviewPageLoadedEvent(
            new AccountOverviewPage(),
            $this->createMock(SalesChannelContext::class),
            $this->createMock(Request::class)
        );

        $settings = new MollieSettingStruct();
        $settings->setTestMode(true);

        $this->settingsServiceMock->method('getSettings')->willReturn($settings);
        $this->subscriber->addTestModeInformationToPages($event);

        self::assertTrue($event->getPage()->isMollieTestMode);
        self::assertTrue(property_exists($event->getPage(), 'isMollieTestMode'));
    }

    public function testAccountPaymentMethodPageDoesContainTestModeInformation(): void
    {
        $event = new AccountPaymentMethodPageLoadedEvent(
            new AccountPaymentMethodPage(),
            $this->createMock(SalesChannelContext::class),
            $this->createMock(Request::class)
        );

        $settings = new MollieSettingStruct();
        $settings->setTestMode(true);

        $this->settingsServiceMock->method('getSettings')->willReturn($settings);
        $this->subscriber->addTestModeInformationToPages($event);

        self::assertTrue($event->getPage()->isMollieTestMode);
        self::assertTrue(property_exists($event->getPage(), 'isMollieTestMode'));
    }

    public function testAccountEditOrderPageDoesContainTestModeInformation(): void
    {
        $event = new AccountEditOrderPageLoadedEvent(
            new AccountEditOrderPage(),
            $this->createMock(SalesChannelContext::class),
            $this->createMock(Request::class)
        );

        $settings = new MollieSettingStruct();
        $settings->setTestMode(true);

        $this->settingsServiceMock->method('getSettings')->willReturn($settings);
        $this->subscriber->addTestModeInformationToPages($event);

        self::assertTrue($event->getPage()->isMollieTestMode);
        self::assertTrue(property_exists($event->getPage(), 'isMollieTestMode'));
    }

    public function testCheckoutConfirmPageDoesContainTestModeInformation(): void
    {
        $event = new CheckoutConfirmPageLoadedEvent(
            new CheckoutConfirmPage(new PaymentMethodCollection([]), new ShippingMethodCollection([])),
            $this->createMock(SalesChannelContext::class),
            $this->createMock(Request::class)
        );

        $settings = new MollieSettingStruct();
        $settings->setTestMode(true);

        $this->settingsServiceMock->method('getSettings')->willReturn($settings);
        $this->subscriber->addTestModeInformationToPages($event);

        self::assertTrue($event->getPage()->isMollieTestMode);
        self::assertTrue(property_exists($event->getPage(), 'isMollieTestMode'));
    }

    public function testCheckoutFinishPageDoesContainTestModeInformation(): void
    {
        $event = new CheckoutFinishPageLoadedEvent(
            new CheckoutFinishPage(),
            $this->createMock(SalesChannelContext::class),
            $this->createMock(Request::class)
        );

        $settings = new MollieSettingStruct();
        $settings->setTestMode(true);

        $this->settingsServiceMock->method('getSettings')->willReturn($settings);
        $this->subscriber->addTestModeInformationToPages($event);

        self::assertTrue($event->getPage()->isMollieTestMode);
        self::assertTrue(property_exists($event->getPage(), 'isMollieTestMode'));
    }

    public function testPagesDoNotContainTestModeInformationIfTestModeIsDisabled(): void
    {
        $event = new CheckoutFinishPageLoadedEvent(
            new CheckoutFinishPage(),
            $this->createMock(SalesChannelContext::class),
            $this->createMock(Request::class)
        );

        $settings = new MollieSettingStruct();
        $settings->setTestMode(false);

        $this->settingsServiceMock->method('getSettings')->willReturn($settings);
        $this->subscriber->addTestModeInformationToPages($event);

        self::assertFalse(property_exists($event->getPage(), 'isMollieTestMode'));
    }
}
