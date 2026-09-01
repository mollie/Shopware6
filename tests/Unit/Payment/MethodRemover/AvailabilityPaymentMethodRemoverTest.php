<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\MethodRemover;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\PaymentLinkGateway;
use Mollie\Shopware\Component\Mollie\PaymentHydrator;
use Mollie\Shopware\Component\Payment\MethodRemover\AvailabilityPaymentMethodRemover;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Builder\PaymentMethodBuilder;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use Mollie\Shopware\Unit\Payment\Fake\FakeCartService;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodHandler;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionAwarePaymentHandler;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\System\Country\CountryEntity;

#[CoversClass(AvailabilityPaymentMethodRemover::class)]
final class AvailabilityPaymentMethodRemoverTest extends TestCase
{
    private MockHandler $mockHandler;

    public function testReturnsAllMethodsWhenLimitsDisabled(): void
    {
        $remover = $this->getRemover(useLimits: false, activeMethodIds: [], cart: $this->buildCart(100.0));

        $result = $remover->remove($this->buildPaymentMethods(), '', new FakeSalesChannelContext());

        $this->assertCount(3, $result);
    }

    public function testRemovesUnavailableMollieMethods(): void
    {
        $remover = $this->getRemover(useLimits: true, activeMethodIds: ['paypal'], cart: $this->buildCart(100.0));

        $result = $remover->remove($this->buildPaymentMethods(), '', new FakeSalesChannelContext());

        $this->assertCount(2, $result);
        $this->assertNotNull($result->get('paypal-id'), 'available mollie method is kept');
        $this->assertNull($result->get('creditcard-id'), 'unavailable mollie method is removed');
        $this->assertNotNull($result->get('non-mollie-id'), 'non mollie method is always kept');
    }

    public function testReturnsAllMethodsWhenCartAmountIsZero(): void
    {
        $remover = $this->getRemover(useLimits: true, activeMethodIds: ['paypal'], cart: $this->buildCart(0.0));

        $result = $remover->remove($this->buildPaymentMethods(), '', new FakeSalesChannelContext());

        $this->assertCount(3, $result);
    }

    public function testOnTheEditOrderPageTheOrderAmountDecidesInsteadOfTheCart(): void
    {
        // The cart is empty on the edit-order page, so the limits have to be checked against the order.
        $orders = new FakeOrderSearchRepository();
        $orders->add($this->buildOrder(100.0, 'NL'));

        $remover = $this->getRemover(useLimits: true, activeMethodIds: ['paypal'], cart: $this->buildCart(0.0), orderRepository: $orders);

        $result = $remover->remove($this->buildPaymentMethods(), 'fakeshopwareorderid', new FakeSalesChannelContext());

        $this->assertNotNull($result->get('paypal-id'), 'available mollie method is kept');
        $this->assertNull($result->get('creditcard-id'), 'unavailable mollie method is removed');
    }

    public function testTheOrderBillingCountryIsSentToMollie(): void
    {
        $orders = new FakeOrderSearchRepository();
        $orders->add($this->buildOrder(100.0, 'NL'));

        $remover = $this->getRemover(useLimits: true, activeMethodIds: ['paypal'], cart: $this->buildCart(0.0), orderRepository: $orders);

        $remover->remove($this->buildPaymentMethods(), 'fakeshopwareorderid', new FakeSalesChannelContext());

        $this->assertSame('NL', $this->lastRequestedBillingCountry());
    }

    public function testAllMethodsStayWhenTheOrderCannotBeLoaded(): void
    {
        $remover = $this->getRemover(useLimits: true, activeMethodIds: ['paypal'], cart: $this->buildCart(100.0), orderRepository: new FakeOrderSearchRepository());

        $result = $remover->remove($this->buildPaymentMethods(), 'unknownorderid', new FakeSalesChannelContext());

        $this->assertCount(3, $result);
    }

    public function testAnOrderWithoutALoadedBillingCountryIsSentWithoutOne(): void
    {
        $orders = new FakeOrderSearchRepository();
        $orders->add($this->buildOrder(100.0, null));

        $remover = $this->getRemover(useLimits: true, activeMethodIds: ['paypal'], cart: $this->buildCart(0.0), orderRepository: $orders);

        $remover->remove($this->buildPaymentMethods(), 'fakeshopwareorderid', new FakeSalesChannelContext());

        $this->assertNull($this->lastRequestedBillingCountry());
    }

    public function testTheCartBillingCountryOfTheSignedInCustomerIsSentToMollie(): void
    {
        $context = new FakeSalesChannelContext();
        $context->setCustomer(CustomerBuilder::create()->withDefaultBillingAddress($this->buildCustomerAddress('BE'))->build());

        $remover = $this->getRemover(useLimits: true, activeMethodIds: ['paypal'], cart: $this->buildCart(100.0));

        $remover->remove($this->buildPaymentMethods(), '', $context);

        $this->assertSame('BE', $this->lastRequestedBillingCountry());
    }

    public function testAGuestWithoutAnAddressIsSentWithoutABillingCountry(): void
    {
        $remover = $this->getRemover(useLimits: true, activeMethodIds: ['paypal'], cart: $this->buildCart(100.0));

        $remover->remove($this->buildPaymentMethods(), '', new FakeSalesChannelContext());

        $this->assertNull($this->lastRequestedBillingCountry());
    }

    public function testACustomerWithoutALoadedCountryIsSentWithoutABillingCountry(): void
    {
        $context = new FakeSalesChannelContext();
        $context->setCustomer(CustomerBuilder::create()->withDefaultBillingAddress($this->buildCustomerAddress(null))->build());

        $remover = $this->getRemover(useLimits: true, activeMethodIds: ['paypal'], cart: $this->buildCart(100.0));

        $remover->remove($this->buildPaymentMethods(), '', $context);

        $this->assertNull($this->lastRequestedBillingCountry());
    }

    private function lastRequestedBillingCountry(): ?string
    {
        $request = $this->mockHandler->getLastRequest();
        $this->assertNotNull($request, 'Mollie was never asked for the active payment methods.');

        $query = [];
        parse_str($request->getUri()->getQuery(), $query);

        return isset($query['billingCountry']) ? (string) $query['billingCountry'] : null;
    }

    private function buildOrder(float $amountTotal, ?string $countryIso): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('fakeshopwareorderid');
        $order->setAmountTotal($amountTotal);

        $billingAddress = new OrderAddressEntity();
        $billingAddress->setId('order-billing-address-id');

        if ($countryIso !== null) {
            $country = new CountryEntity();
            $country->setId('country-id');
            $country->setIso($countryIso);
            $billingAddress->setCountry($country);
        }

        $order->setBillingAddress($billingAddress);

        return $order;
    }

    private function buildCustomerAddress(?string $countryIso): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setId('customer-billing-address-id');

        if ($countryIso !== null) {
            $country = new CountryEntity();
            $country->setId('country-id');
            $country->setIso($countryIso);
            $address->setCountry($country);
        }

        return $address;
    }

    private function getRemover(bool $useLimits, array $activeMethodIds, Cart $cart, ?FakeOrderSearchRepository $orderRepository = null): AvailabilityPaymentMethodRemover
    {
        $methods = array_map(function (string $id): array {
            return ['id' => $id];
        }, $activeMethodIds);

        $this->mockHandler = new MockHandler([
            new Response(200, [], (string) json_encode(['_embedded' => ['methods' => $methods]])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
        $gateway = new MollieGateway(new FakeClientFactory($client), new FakeTransactionService(), new PaymentLinkGateway(new FakeClientFactory($client), new PaymentHydrator(), new NullLogger()), new PaymentHydrator(), new NullLogger());

        $handlerLocator = new PaymentHandlerLocator([
            new FakePaymentMethodHandler(),
            new FakeSubscriptionAwarePaymentHandler(),
        ]);

        $settingsService = new FakeSettingsService(paymentSettings: new PaymentSettings('', 0, useMollieLimits: $useLimits));

        return new AvailabilityPaymentMethodRemover(
            $handlerLocator,
            $gateway,
            new FakeCartService($cart),
            $orderRepository ?? new FakeOrderSearchRepository(),
            $settingsService
        );
    }

    private function buildPaymentMethods(): PaymentMethodCollection
    {
        $paypal = PaymentMethodBuilder::create()
            ->withId('paypal-id')
            ->withHandlerIdentifier(FakePaymentMethodHandler::class)
            ->build()
        ;

        $creditcard = PaymentMethodBuilder::create()
            ->withId('creditcard-id')
            ->withHandlerIdentifier(FakeSubscriptionAwarePaymentHandler::class)
            ->build()
        ;

        $nonMollie = PaymentMethodBuilder::create()
            ->withId('non-mollie-id')
            ->withHandlerIdentifier('Some\Other\PaymentHandler')
            ->build()
        ;

        return new PaymentMethodCollection([$paypal, $creditcard, $nonMollie]);
    }

    private function buildCart(float $totalPrice): Cart
    {
        $cart = CartBuilder::create()->build();
        $cart->setPrice(new CartPrice(
            $totalPrice,
            $totalPrice,
            $totalPrice,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS
        ));

        return $cart;
    }
}
