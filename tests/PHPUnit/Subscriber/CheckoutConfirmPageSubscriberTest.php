<?php

namespace MolliePayments\Tests\Subscriber;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\Payment\Provider\ActivePaymentMethodsProvider;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Subscriber\CheckoutConfirmPageSubscriber;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;

class CheckoutConfirmPageSubscriberTest extends TestCase
{
    /** @var CheckoutConfirmPage */
    private $checkoutConfirmPage;

    private PaymentMethodCollection $paymentMethodCollection;
    /**
     * Sets up the test.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->paymentMethodCollection = new PaymentMethodCollection();
    }

    /**
     * Sets up the active payment methods provider for certain payment methods.
     *
     * @param array $methods
     * @return ActivePaymentMethodsProvider
     */
    public function setUpActivePaymentMethodsProvider(array $methods): ActivePaymentMethodsProvider
    {
        return $this->createConfiguredMock(ActivePaymentMethodsProvider::class, [
            'getActivePaymentMethodsForAmount' => $methods,
        ]);
    }

    /**
     * Sets up the available payment methods from Mollie.
     *
     * @param array $methods
     * @return array
     */
    public function setUpAvailableMethods(array $methods): array
    {
        $client = $this->createMock(MollieApiClient::class);

        $methodObjects = [];

        foreach ($methods as $method) {
            $methodObject = new Method($client);
            $methodObject->id = $method;

            $methodObjects[] = $methodObject;
        }

        return $methodObjects;
    }

    /**
     * Sets op the event args for the checkout confirm page.
     *
     * @param array $paymentHandlers
     * @return CheckoutConfirmPageLoadedEvent
     */
    public function setUpArgs(array $paymentHandlers = []): CheckoutConfirmPageLoadedEvent
    {
        $paymentMethodCollection = new PaymentMethodCollection();

        foreach ($paymentHandlers as $paymentHandler) {
            $paymentMethodCollection->add(
                $this->createConfiguredMock(PaymentMethodEntity::class, [
                    'getHandlerIdentifier' => $paymentHandler,
                    'getUniqueIdentifier' => Uuid::randomHex(),
                ])
            );
        }

        return $this->createConfiguredMock(CheckoutConfirmPageLoadedEvent::class, [
            'getPage' => $this->setUpCheckoutConfirmPage($paymentMethodCollection),
            'getSalesChannelContext' => $this->setUpSalesChannelContext(),
        ]);
    }

    /**
     * Sets up the cart for a certain amount.
     *
     * @param float $totalAmount
     * @return Cart
     */
    public function setUpCart(float $totalAmount = 100.0): Cart
    {
        $price = $this->createConfiguredMock(CartPrice::class, [
            'getTotalPrice' => $totalAmount,
        ]);

        return $this->createConfiguredMock(Cart::class, [
            'getPrice' => $price,
        ]);
    }

    /**
     * Sets up the checkout confirm page.
     *
     * @param PaymentMethodCollection $paymentMethodCollection
     * @return CheckoutConfirmPage
     */
    public function setUpCheckoutConfirmPage(PaymentMethodCollection $paymentMethodCollection): CheckoutConfirmPage
    {
        $this->checkoutConfirmPage = new CheckoutConfirmPage();

        $this->checkoutConfirmPage->setCart($this->setUpCart());
        $this->checkoutConfirmPage->setPaymentMethods($paymentMethodCollection);

        return $this->checkoutConfirmPage;
    }

    /**
     * Sets up the currency entity for a certain iso code.
     *
     * @param string $isoCode
     * @return CurrencyEntity
     */
    public function setUpCurrency(string $isoCode = 'NL'): CurrencyEntity
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode(strtoupper($isoCode));

        return $currency;
    }

    /**
     * Sets up the sales channel context.
     *
     * @return SalesChannelContext
     */
    public function setUpSalesChannelContext(): SalesChannelContext
    {
        return $this->createConfiguredMock(SalesChannelContext::class, [
            'getCurrency' => $this->setUpCurrency(),
            'getSalesChannel' => $this->createMock(SalesChannelEntity::class),
        ]);
    }

    /**
     * Sets up the settings service.
     *
     * @param bool $limitsEnabled
     * @return SettingsService
     */
    public function setUpSettingsService(bool $limitsEnabled = true): SettingsService
    {
        $mollieSettings = $this->createConfiguredMock(MollieSettingStruct::class, [
            'getUseMolliePaymentMethodLimits' => $limitsEnabled,
        ]);

        return $this->createConfiguredMock(SettingsService::class, [
            'getSettings' => $mollieSettings,
        ]);
    }

    /**
     * Sets up the checkout confirm page subscriber.
     *
     * @param array $availablePaymentMethods
     * @param bool $limitsEnabled
     * @return CheckoutConfirmPageSubscriber
     */
    public function setUpSubscriber(array $availablePaymentMethods, bool $limitsEnabled = true): CheckoutConfirmPageSubscriber
    {
        return new CheckoutConfirmPageSubscriber(
            $this->createMock(MollieApiFactory::class),
            $this->setUpSettingsService($limitsEnabled),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->setUpActivePaymentMethodsProvider($availablePaymentMethods)
        );
    }

    /**
     * Test if subscribed events contains checkout confirm page loaded event.
     *
     * @testdox Subscriber has the expected subscribed events.
     * @return void
     */
    public function testSubscribedEvents(): void
    {
        $expected = CheckoutConfirmPageLoadedEvent::class;

        self::assertArrayHasKey($expected, CheckoutConfirmPageSubscriber::getSubscribedEvents());
    }
}
