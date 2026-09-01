<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Mollie\SessionStatus;
use Mollie\Shopware\Component\Mollie\ShippingOption;
use Mollie\Shopware\Component\Mollie\ShippingOptionCollection;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionBuilder;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Entity\Customer\Customer;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\FakeCartPersister;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeOrderRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeSessionLineBuilder;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeShippingOptionsResolver;
use Mollie\Shopware\Unit\Payment\Fake\FakeSessionGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

#[CoversClass(SessionBuilder::class)]
final class SessionBuilderTest extends TestCase
{
    private const PROFILE_ID = 'pfl_test_profile';

    private FakeSessionGateway $sessionGateway;
    private FakeSessionLineBuilder $lineBuilder;
    private FakeShippingOptionsResolver $shippingOptionsResolver;
    private FakeRouteBuilder $routeBuilder;
    private FakeSettingsService $settings;
    private FakeCartPersister $cartPersister;
    private FakeOrderRepository $orderRepository;
    private FakeLogger $logger;

    protected function setUp(): void
    {
        $this->sessionGateway = new FakeSessionGateway($this->createSession('ses_created'));
        $this->lineBuilder = new FakeSessionLineBuilder();
        $this->shippingOptionsResolver = new FakeShippingOptionsResolver();
        $this->routeBuilder = new FakeRouteBuilder(expressComponentsRedirectUrl: 'https://shop.test/mollie/express/return');
        $this->settings = new FakeSettingsService(apiSettings: new ApiSettings('test_key', 'live_key', Mode::TEST, self::PROFILE_ID));
        $this->cartPersister = new FakeCartPersister();
        $this->orderRepository = new FakeOrderRepository();
        $this->logger = new FakeLogger();
    }

    public function testCartSessionAmountLeavesOutTheShippingCosts(): void
    {
        $cart = $this->createGrossCart(119.00, 5.95);
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($cart, $this->createSalesChannelContext());

        $this->assertSame(113.05, $this->sessionGateway->getLastCreateSession()->getAmount()->getValue());
    }

    public function testCartSessionAmountOfANetCartLeavesOutTheShippingTaxAsWell(): void
    {
        $cart = CartBuilder::create()
            ->withPrice(new CartPrice(100.00, 119.00, 100.00, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET))
            ->withShippingCosts(new CalculatedPrice(5.00, 5.00, new CalculatedTaxCollection([new CalculatedTax(0.95, 19.0, 5.00)]), new TaxRuleCollection()))
            ->build()
        ;
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($cart, $this->createSalesChannelContext());

        $this->assertSame(113.05, $this->sessionGateway->getLastCreateSession()->getAmount()->getValue());
    }

    public function testCartSessionLinesAreBuiltForTheAmountWithoutShipping(): void
    {
        $cart = $this->createGrossCart(119.00, 5.95);
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($cart, $this->createSalesChannelContext());

        $this->assertSame(113.05, $this->lineBuilder->getLastAmount()->getValue());
    }

    public function testCartSessionAmountUsesTheCurrencyOfTheContext(): void
    {
        $context = $this->createSalesChannelContext();
        $context->setCurrency($this->createCurrency('CHF'));
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($this->createGrossCart(119.00, 5.95), $context);

        $this->assertSame('CHF', $this->sessionGateway->getLastCreateSession()->getAmount()->getCurrency());
    }

    public function testCreatedCartSessionIsKeptOnTheCartForTheCurrentMode(): void
    {
        $cart = $this->createGrossCart(119.00, 5.95);
        $sessionBuilder = $this->createSessionBuilder();

        $session = $sessionBuilder->buildFromCart($cart, $this->createSalesChannelContext());

        $this->assertSame($session, $cart->getExtension(SessionBuilder::cartExtensionKey(Mode::TEST)));
    }

    public function testCreatedCartSessionIsPersistedWithTheCart(): void
    {
        $cart = $this->createGrossCart(119.00, 5.95);
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($cart, $this->createSalesChannelContext());

        $this->assertSame(1, $this->cartPersister->getSaveCount());
    }

    public function testStoredCartSessionIsReusedWhileItStillMatchesTheCartTotal(): void
    {
        $storedSession = $this->createSession('ses_stored', new Money(113.05, 'EUR'));
        $this->sessionGateway->setExistingSession($storedSession);
        $cart = $this->createGrossCart(119.00, 5.95);
        $cart->addExtension(SessionBuilder::cartExtensionKey(Mode::TEST), $storedSession);
        $sessionBuilder = $this->createSessionBuilder();

        $session = $sessionBuilder->buildFromCart($cart, $this->createSalesChannelContext());

        $this->assertSame('ses_stored', $session->getId());
        $this->assertSame(0, $this->sessionGateway->getCreateSessionCount());
    }

    public function testStoredCartSessionIsReplacedAfterTheCartTotalChanged(): void
    {
        $storedSession = $this->createSession('ses_stored', new Money(99.99, 'EUR'));
        $this->sessionGateway->setExistingSession($storedSession);
        $cart = $this->createGrossCart(119.00, 5.95);
        $cart->addExtension(SessionBuilder::cartExtensionKey(Mode::TEST), $storedSession);
        $sessionBuilder = $this->createSessionBuilder();

        $session = $sessionBuilder->buildFromCart($cart, $this->createSalesChannelContext());

        $this->assertSame('ses_created', $session->getId());
    }

    public function testStoredCartSessionInAnotherCurrencyIsReplaced(): void
    {
        $storedSession = $this->createSession('ses_stored', new Money(113.05, 'USD'));
        $this->sessionGateway->setExistingSession($storedSession);
        $cart = $this->createGrossCart(119.00, 5.95);
        $cart->addExtension(SessionBuilder::cartExtensionKey(Mode::TEST), $storedSession);
        $sessionBuilder = $this->createSessionBuilder();

        $session = $sessionBuilder->buildFromCart($cart, $this->createSalesChannelContext());

        $this->assertSame('ses_created', $session->getId());
    }

    public function testStoredCartSessionWithoutAnAmountIsReplaced(): void
    {
        $storedSession = $this->createSession('ses_stored');
        $this->sessionGateway->setExistingSession($storedSession);
        $cart = $this->createGrossCart(119.00, 5.95);
        $cart->addExtension(SessionBuilder::cartExtensionKey(Mode::TEST), $storedSession);
        $sessionBuilder = $this->createSessionBuilder();

        $session = $sessionBuilder->buildFromCart($cart, $this->createSalesChannelContext());

        $this->assertSame('ses_created', $session->getId());
    }

    public function testStoredCartSessionThatCannotBeLoadedIsReplaced(): void
    {
        $this->sessionGateway->failGetSessionWith(new \RuntimeException('session is gone'));
        $cart = $this->createGrossCart(119.00, 5.95);
        $cart->addExtension(SessionBuilder::cartExtensionKey(Mode::TEST), $this->createSession('ses_stored', new Money(113.05, 'EUR')));
        $sessionBuilder = $this->createSessionBuilder();

        $session = $sessionBuilder->buildFromCart($cart, $this->createSalesChannelContext());

        $this->assertSame('ses_created', $session->getId());
        $this->assertTrue($this->logger->hasRecordThatContains('warning', 'Stored express components session could not be loaded'));
    }

    public function testCartSessionOfTheOtherModeIsNotReused(): void
    {
        $storedSession = $this->createSession('ses_stored', new Money(113.05, 'EUR'));
        $this->sessionGateway->setExistingSession($storedSession);
        $cart = $this->createGrossCart(119.00, 5.95);
        $cart->addExtension(SessionBuilder::cartExtensionKey(Mode::LIVE), $storedSession);
        $sessionBuilder = $this->createSessionBuilder();

        $session = $sessionBuilder->buildFromCart($cart, $this->createSalesChannelContext());

        $this->assertSame('ses_created', $session->getId());
    }

    public function testCartSessionIsDescribedByTheSalesChannelName(): void
    {
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($this->createGrossCart(119.00, 5.95), $this->createSalesChannelContext());

        $this->assertSame('Fake Sales Channel', $this->sessionGateway->getLastCreateSession()->getDescription());
    }

    public function testCartSessionReturnsToTheExpressComponentsRoute(): void
    {
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($this->createGrossCart(119.00, 5.95), $this->createSalesChannelContext());

        $this->assertSame('https://shop.test/mollie/express/return', $this->sessionGateway->getLastCreateSession()->getRedirectUrl());
    }

    public function testCartSessionAsksTheWalletForEmailAndBothAddresses(): void
    {
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($this->createGrossCart(119.00, 5.95), $this->createSalesChannelContext());

        $this->assertSame(['email', 'billing-address', 'shipping-address'], $this->sessionGateway->getLastCreateSession()->getRequiredCustomerDetails());
    }

    public function testCartSessionShippingOptionsAreResolvedForTheShippingCountryOfTheContext(): void
    {
        $context = $this->createSalesChannelContext();
        $context->setShippingLocation(ShippingLocation::createFromCountry($this->createCountry('NL')));
        $this->shippingOptionsResolver = new FakeShippingOptionsResolver(new ShippingOptionCollection([
            new ShippingOption('Standard', 'shipping-method-id', new Money(5.95, 'EUR')),
        ]));
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($this->createGrossCart(119.00, 5.95), $context);

        $this->assertSame('NL', $this->shippingOptionsResolver->getLastAddress()->getCountry());
        $this->assertSame(1, $this->sessionGateway->getLastCreateSession()->getShippingOptions()?->count());
    }

    public function testGuestCartSessionCarriesNoAddresses(): void
    {
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($this->createGrossCart(119.00, 5.95), $this->createSalesChannelContext());

        $createSession = $this->sessionGateway->getLastCreateSession();
        $this->assertNull($createSession->getBillingAddress());
        $this->assertNull($createSession->getShippingAddress());
        $this->assertNull($createSession->getCustomerId());
    }

    public function testActiveAddressesOfTheLoggedInCustomerAreSentWithTheCartSession(): void
    {
        $customer = CustomerBuilder::create()
            ->withEmail('shopper@example.com')
            ->withActiveBillingAddress($this->createAddress('Billing Street 1'))
            ->withActiveShippingAddress($this->createAddress('Shipping Street 2'))
            ->build()
        ;
        $context = $this->createSalesChannelContext();
        $context->setCustomer($customer);
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($this->createGrossCart(119.00, 5.95), $context);

        $createSession = $this->sessionGateway->getLastCreateSession();
        $this->assertSame('Billing Street 1', $createSession->getBillingAddress()?->getStreetAndNumber());
        $this->assertSame('Shipping Street 2', $createSession->getShippingAddress()?->getStreetAndNumber());
    }

    public function testDefaultAddressesAreSentWhenTheCheckoutHasNoActiveAddress(): void
    {
        $customer = CustomerBuilder::create()
            ->withDefaultBillingAddress($this->createAddress('Default Billing 1'))
            ->withDefaultShippingAddress($this->createAddress('Default Shipping 2'))
            ->build()
        ;
        $context = $this->createSalesChannelContext();
        $context->setCustomer($customer);
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($this->createGrossCart(119.00, 5.95), $context);

        $createSession = $this->sessionGateway->getLastCreateSession();
        $this->assertSame('Default Billing 1', $createSession->getBillingAddress()?->getStreetAndNumber());
        $this->assertSame('Default Shipping 2', $createSession->getShippingAddress()?->getStreetAndNumber());
    }

    public function testCustomerEmailIsUsedForAnAddressThatHasNoCustomerAssociated(): void
    {
        $customer = CustomerBuilder::create()
            ->withEmail('shopper@example.com')
            ->withActiveBillingAddress($this->createAddress('Billing Street 1'))
            ->build()
        ;
        $context = $this->createSalesChannelContext();
        $context->setCustomer($customer);
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($this->createGrossCart(119.00, 5.95), $context);

        $this->assertSame('shopper@example.com', $this->sessionGateway->getLastCreateSession()->getBillingAddress()?->getEmail());
    }

    public function testMollieCustomerIdOfTheCurrentProfileAndModeIsSentWithTheCartSession(): void
    {
        $mollieCustomer = new Customer();
        $mollieCustomer->setCustomerId(self::PROFILE_ID, Mode::TEST, 'cst_test_shopper');
        $customer = CustomerBuilder::create()->build();
        $customer->addExtension(Mollie::EXTENSION, $mollieCustomer);
        $context = $this->createSalesChannelContext();
        $context->setCustomer($customer);
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($this->createGrossCart(119.00, 5.95), $context);

        $this->assertSame('cst_test_shopper', $this->sessionGateway->getLastCreateSession()->getCustomerId());
    }

    public function testMollieCustomerIdOfAnotherProfileIsNotSentWithTheCartSession(): void
    {
        $mollieCustomer = new Customer();
        $mollieCustomer->setCustomerId('pfl_other_profile', Mode::TEST, 'cst_other_shopper');
        $customer = CustomerBuilder::create()->build();
        $customer->addExtension(Mollie::EXTENSION, $mollieCustomer);
        $context = $this->createSalesChannelContext();
        $context->setCustomer($customer);
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($this->createGrossCart(119.00, 5.95), $context);

        $this->assertNull($this->sessionGateway->getLastCreateSession()->getCustomerId());
    }

    public function testCustomerWithoutAMollieExtensionSendsNoCustomerId(): void
    {
        $context = $this->createSalesChannelContext();
        $context->setCustomer(CustomerBuilder::create()->build());
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromCart($this->createGrossCart(119.00, 5.95), $context);

        $this->assertNull($this->sessionGateway->getLastCreateSession()->getCustomerId());
    }

    public function testOrderSessionAmountLeavesOutTheShippingCosts(): void
    {
        $order = $this->createOrder(119.00, 5.95);
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromOrder($order, $this->createSalesChannelContext());

        $this->assertSame(113.05, $this->sessionGateway->getLastCreateSession()->getAmount()->getValue());
    }

    public function testOrderSessionAmountOfANetOrderLeavesOutTheShippingTaxAsWell(): void
    {
        $order = $this->createOrder(119.00, 5.00, CartPrice::TAX_STATE_NET, new CalculatedTaxCollection([new CalculatedTax(0.95, 19.0, 5.00)]));
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromOrder($order, $this->createSalesChannelContext());

        $this->assertSame(113.05, $this->sessionGateway->getLastCreateSession()->getAmount()->getValue());
    }

    public function testTaxFreeOrderSessionUsesTheNetAmountOfTheOrder(): void
    {
        $order = $this->createOrder(119.00, 5.95, CartPrice::TAX_STATE_FREE);
        $order->setAmountNet(100.00);
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromOrder($order, $this->createSalesChannelContext());

        $this->assertSame(94.05, $this->sessionGateway->getLastCreateSession()->getAmount()->getValue());
    }

    /**
     * Without the currency association the net amount of a tax free order cannot be picked, so the
     * total is used - the criteria of the caller decides which of the two amounts the shopper sees.
     */
    public function testOrderWithoutAssociatedCurrencyFallsBackToItsTotal(): void
    {
        $order = $this->createOrder(119.00, 5.95, CartPrice::TAX_STATE_FREE);
        $order->setAmountNet(100.00);
        // the setter of the association is not nullable, so the loaded currency is removed again
        $order->assign(['currency' => null]);
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromOrder($order, $this->createSalesChannelContext());

        $this->assertSame(113.05, $this->sessionGateway->getLastCreateSession()->getAmount()->getValue());
    }

    public function testCreatedOrderSessionIdIsStoredOnTheOrderForTheCurrentMode(): void
    {
        $order = $this->createOrder(119.00, 5.95);
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromOrder($order, $this->createSalesChannelContext());

        $this->assertSame([
            'id' => 'order-id',
            'customFields' => [
                SessionBuilder::ORDER_CUSTOM_FIELD => [
                    'test' => [SessionBuilder::ORDER_CUSTOM_FIELD_SESSION_ID => 'ses_created'],
                ],
            ],
        ], $this->orderRepository->getLastUpdate());
    }

    public function testStoringAnOrderSessionKeepsTheSessionOfTheOtherMode(): void
    {
        $order = $this->createOrder(119.00, 5.95);
        $order->setCustomFields([
            SessionBuilder::ORDER_CUSTOM_FIELD => [
                'live' => [SessionBuilder::ORDER_CUSTOM_FIELD_SESSION_ID => 'ses_live'],
            ],
        ]);
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromOrder($order, $this->createSalesChannelContext());

        $this->assertSame([
            'live' => [SessionBuilder::ORDER_CUSTOM_FIELD_SESSION_ID => 'ses_live'],
            'test' => [SessionBuilder::ORDER_CUSTOM_FIELD_SESSION_ID => 'ses_created'],
        ], $this->orderRepository->getLastUpdate()['customFields'][SessionBuilder::ORDER_CUSTOM_FIELD]);
    }

    public function testStoredOrderSessionIsReusedWhileItStillMatchesTheOrderTotal(): void
    {
        $this->sessionGateway->setExistingSession($this->createSession('ses_stored', new Money(113.05, 'EUR')));
        $order = $this->createOrderWithStoredSession('ses_stored');
        $sessionBuilder = $this->createSessionBuilder();

        $session = $sessionBuilder->buildFromOrder($order, $this->createSalesChannelContext());

        $this->assertSame('ses_stored', $session->getId());
        $this->assertSame(0, $this->sessionGateway->getCreateSessionCount());
    }

    public function testCompletedOrderSessionIsNotOfferedAgain(): void
    {
        $completedSession = $this->createSession('ses_stored', new Money(113.05, 'EUR'));
        $completedSession->setStatus(SessionStatus::COMPLETED);
        $this->sessionGateway->setExistingSession($completedSession);
        $order = $this->createOrderWithStoredSession('ses_stored');
        $sessionBuilder = $this->createSessionBuilder();

        $session = $sessionBuilder->buildFromOrder($order, $this->createSalesChannelContext());

        $this->assertSame('ses_created', $session->getId());
    }

    public function testStoredOrderSessionIsReplacedAfterTheOrderTotalChanged(): void
    {
        $this->sessionGateway->setExistingSession($this->createSession('ses_stored', new Money(99.99, 'EUR')));
        $order = $this->createOrderWithStoredSession('ses_stored');
        $sessionBuilder = $this->createSessionBuilder();

        $session = $sessionBuilder->buildFromOrder($order, $this->createSalesChannelContext());

        $this->assertSame('ses_created', $session->getId());
    }

    public function testStoredOrderSessionThatCannotBeLoadedIsReplaced(): void
    {
        $this->sessionGateway->failGetSessionWith(new \RuntimeException('session is gone'));
        $order = $this->createOrderWithStoredSession('ses_stored');
        $sessionBuilder = $this->createSessionBuilder();

        $session = $sessionBuilder->buildFromOrder($order, $this->createSalesChannelContext());

        $this->assertSame('ses_created', $session->getId());
        $this->assertTrue($this->logger->hasRecordThatContains('warning', 'Stored express components order session could not be loaded'));
    }

    public function testOrderSessionOffersTheShippingMethodTheCustomerAlreadyDecidedOn(): void
    {
        $order = $this->createOrder(119.00, 5.95, shippingMethodName: 'DHL Express');
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromOrder($order, $this->createSalesChannelContext());

        $shippingOptions = $this->sessionGateway->getLastCreateSession()->getShippingOptions();
        $shippingOption = $shippingOptions?->first();
        $this->assertSame('DHL Express', $shippingOption?->getDescription());
        $this->assertSame('shipping-method-id', $shippingOption?->getReference());
        $this->assertSame(5.95, $shippingOption?->getAmount()->getValue());
    }

    public function testOrderShippingOptionWithoutAShippingMethodNameFallsBackToAGenericDescription(): void
    {
        $order = $this->createOrder(119.00, 5.95, shippingMethodName: '  ');
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromOrder($order, $this->createSalesChannelContext());

        $this->assertSame('Shipping', $this->sessionGateway->getLastCreateSession()->getShippingOptions()?->first()?->getDescription());
    }

    public function testOrderDeliveryWithoutAShippingMethodIsNotOfferedAsAnOption(): void
    {
        $order = $this->createOrder(119.00, 5.95);
        $delivery = new OrderDeliveryEntity();
        $delivery->setId('delivery-without-shipping-method');
        $delivery->setShippingCosts($this->createShippingCosts(0.0));
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromOrder($order, $this->createSalesChannelContext());

        $this->assertSame(0, $this->sessionGateway->getLastCreateSession()->getShippingOptions()?->count());
    }

    public function testOrderWithoutDeliveriesOffersNoShippingOption(): void
    {
        $order = $this->createOrder(119.00, 5.95);
        // a digital order has no delivery at all, the setter of the association is not nullable
        $order->assign(['deliveries' => null]);
        $sessionBuilder = $this->createSessionBuilder();

        $sessionBuilder->buildFromOrder($order, $this->createSalesChannelContext());

        $this->assertSame(0, $this->sessionGateway->getLastCreateSession()->getShippingOptions()?->count());
    }

    /**
     * @param array<mixed> $customFields
     */
    #[DataProvider('orderCustomFieldsWithoutAUsableSessionProvider')]
    public function testOrderWithoutAUsableStoredSessionIdReportsNone(array $customFields): void
    {
        $this->assertNull(SessionBuilder::readOrderSessionId($customFields, Mode::TEST));
    }

    /**
     * @return array<string, array{array<mixed>}>
     */
    public static function orderCustomFieldsWithoutAUsableSessionProvider(): array
    {
        return [
            'no custom fields at all' => [[]],
            'no session for the current mode' => [[SessionBuilder::ORDER_CUSTOM_FIELD => ['live' => [SessionBuilder::ORDER_CUSTOM_FIELD_SESSION_ID => 'ses_live']]]],
            'empty session id' => [[SessionBuilder::ORDER_CUSTOM_FIELD => ['test' => [SessionBuilder::ORDER_CUSTOM_FIELD_SESSION_ID => '']]]],
            'session id is not a string' => [[SessionBuilder::ORDER_CUSTOM_FIELD => ['test' => [SessionBuilder::ORDER_CUSTOM_FIELD_SESSION_ID => 42]]]],
            'custom field is not an array' => [[SessionBuilder::ORDER_CUSTOM_FIELD => 'ses_broken']],
        ];
    }

    public function testStoredSessionIdOfTheCurrentModeIsReported(): void
    {
        $customFields = [SessionBuilder::ORDER_CUSTOM_FIELD => ['test' => [SessionBuilder::ORDER_CUSTOM_FIELD_SESSION_ID => 'ses_stored']]];

        $this->assertSame('ses_stored', SessionBuilder::readOrderSessionId($customFields, Mode::TEST));
    }

    public function testTestAndLiveSessionsAreKeptApartOnTheCart(): void
    {
        $this->assertNotSame(SessionBuilder::cartExtensionKey(Mode::TEST), SessionBuilder::cartExtensionKey(Mode::LIVE));
    }

    private function createSessionBuilder(): SessionBuilder
    {
        return new SessionBuilder(
            $this->sessionGateway,
            $this->lineBuilder,
            $this->shippingOptionsResolver,
            $this->routeBuilder,
            $this->settings,
            $this->cartPersister,
            $this->orderRepository,
            $this->logger
        );
    }

    private function createSalesChannelContext(): FakeSalesChannelContext
    {
        $context = new FakeSalesChannelContext();
        $context->setShippingLocation(ShippingLocation::createFromCountry($this->createCountry('DE')));

        return $context;
    }

    private function createSession(string $id, ?Money $amount = null): Session
    {
        $session = new Session($id);
        if ($amount instanceof Money) {
            $session->setAmount($amount);
        }

        return $session;
    }

    private function createGrossCart(float $total, float $shippingCosts): Cart
    {
        return CartBuilder::create()
            ->withPrice(new CartPrice($total, $total, $total, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS))
            ->withShippingCosts($this->createShippingCosts($shippingCosts))
            ->build()
        ;
    }

    private function createShippingCosts(float $total, ?CalculatedTaxCollection $taxes = null): CalculatedPrice
    {
        return new CalculatedPrice($total, $total, $taxes ?? new CalculatedTaxCollection(), new TaxRuleCollection());
    }

    private function createOrderWithStoredSession(string $sessionId): OrderEntity
    {
        $order = $this->createOrder(119.00, 5.95);
        $order->setCustomFields([
            SessionBuilder::ORDER_CUSTOM_FIELD => [
                'test' => [SessionBuilder::ORDER_CUSTOM_FIELD_SESSION_ID => $sessionId],
            ],
        ]);

        return $order;
    }

    private function createOrder(
        float $amountTotal,
        float $shippingCosts,
        string $taxStatus = CartPrice::TAX_STATE_GROSS,
        ?CalculatedTaxCollection $shippingTaxes = null,
        string $shippingMethodName = 'Standard'
    ): OrderEntity {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('shipping-method-id');
        $shippingMethod->setName($shippingMethodName);

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('delivery-id');
        $delivery->setShippingMethod($shippingMethod);
        $delivery->setShippingCosts($this->createShippingCosts($shippingCosts, $shippingTaxes));

        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setAmountTotal($amountTotal);
        $order->setAmountNet($amountTotal);
        $order->setTaxStatus($taxStatus);
        $order->setShippingCosts($this->createShippingCosts($shippingCosts, $shippingTaxes));
        $order->setCurrency($this->createCurrency('EUR'));
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        return $order;
    }

    private function createCurrency(string $isoCode): CurrencyEntity
    {
        $currency = new CurrencyEntity();
        $currency->setId('currency-id');
        $currency->setIsoCode($isoCode);

        return $currency;
    }

    private function createCountry(string $iso): CountryEntity
    {
        $country = new CountryEntity();
        $country->setId('country-id');
        $country->setIso($iso);

        return $country;
    }

    private function createAddress(string $street): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setId('address-' . $street);
        $address->setFirstName('Test');
        $address->setLastName('Customer');
        $address->setStreet($street);
        $address->setZipcode('12345');
        $address->setCity('Test City');
        $address->setCountry($this->createCountry('DE'));

        return $address;
    }
}
