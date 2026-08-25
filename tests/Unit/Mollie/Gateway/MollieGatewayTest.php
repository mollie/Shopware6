<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreateCapture;
use Mollie\Shopware\Component\Mollie\CreateOrder;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\CreateShipment;
use Mollie\Shopware\Component\Mollie\Exception\ApiException;
use Mollie\Shopware\Component\Mollie\Exception\TransactionWithoutMollieDataException;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\PaymentLinkGateway;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Locale;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentHydrator;
use Mollie\Shopware\Component\Mollie\ShippingItem;
use Mollie\Shopware\Component\Mollie\ShippingItemCollection;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClient;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;

#[CoversClass(MollieGateway::class)]
final class MollieGatewayTest extends TestCase
{
    public function testLoadPaymentFromApi(): void
    {
        $fakeClient = new FakeClient('mollieTestId', 'paid');
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();

        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new PaymentHydrator(), new NullLogger()), new PaymentHydrator(), new NullLogger());

        $context = new Context(new SystemSource());

        $actual = $gateway->getPaymentByTransactionId('test', $context);

        $this->assertInstanceOf(Payment::class, $actual);
        $this->assertSame('mollieTestId',$actual->getId());
        $this->assertSame('paid', $actual->getStatus()->value);
    }

    public function testPaymentIsLoadedByOrderEntity(): void
    {
        $fakeClient = new FakeClient('mollieTestId', 'paid', embed: true);
        $fakeClientFactory = new FakeClientFactory($fakeClient);

        $transactionService = new FakeTransactionService();
        $transactionService->withOrderCustomFields([
            'order_id' => 'mollieTestId',
            'transactionReturnUrl' => 'payment/finalize',
        ]);

        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new PaymentHydrator(), new NullLogger()), new PaymentHydrator(), new NullLogger());

        $context = new Context(new SystemSource());

        $actual = $gateway->getPaymentByTransactionId('test', $context);

        $this->assertInstanceOf(Payment::class, $actual);
        $this->assertSame('mollieTestId',$actual->getId());
        $this->assertSame('paid',$actual->getStatus()->value);
    }

    public function testLoadingPaymentByOrderThrowsException(): void
    {
        $this->expectException(ApiException::class);
        $fakeClient = new FakeClient();
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $transactionService = new FakeTransactionService();
        $transactionService->withOrderCustomFields([
            'order_id' => 'mollieTestId',
            'transactionReturnUrl' => 'payment/finalize',
        ]);

        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new PaymentHydrator(), new NullLogger()), new PaymentHydrator(), new NullLogger());

        $context = new Context(new SystemSource());

        $gateway->getPaymentByTransactionId('test', $context);
    }

    public function testTransactionWithoutMollieDataThrowsException(): void
    {
        $this->expectException(TransactionWithoutMollieDataException::class);
        $fakeClient = new FakeClient();
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $transactionService = new FakeTransactionService();

        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new PaymentHydrator(), new NullLogger()), new PaymentHydrator(), new NullLogger());

        $context = new Context(new SystemSource());
        $gateway->getPaymentByTransactionId('test', $context);
    }

    public function testEmptyMollieOrderIdThrowsException(): void
    {
        $this->expectException(TransactionWithoutMollieDataException::class);
        $fakeClient = new FakeClient();
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $transactionService = new FakeTransactionService();
        $transactionService->withOrderCustomFields([
            'transactionReturnUrl' => 'payment/finalize',
        ]);
        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new PaymentHydrator(), new NullLogger()), new PaymentHydrator(), new NullLogger());

        $context = new Context(new SystemSource());
        $gateway->getPaymentByTransactionId('test', $context);
    }

    public function testEmptyTransactionUrlThrowsException(): void
    {
        $this->expectException(TransactionWithoutMollieDataException::class);
        $fakeClient = new FakeClient();
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $transactionService = new FakeTransactionService();

        $transactionService->withOrderCustomFields([
            'order_id' => 'test',
        ]);
        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new PaymentHydrator(), new NullLogger()), new PaymentHydrator(), new NullLogger());

        $context = new Context(new SystemSource());
        $gateway->getPaymentByTransactionId('test', $context);
    }

    public function testLoadPaymentThrowsException(): void
    {
        $this->expectException(ApiException::class);

        $fakeClient = new FakeClient();
        $transactionService = new FakeTransactionService();
        $fakeClientFactory = new FakeClientFactory($fakeClient);

        $transactionService->createValidStruct();
        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new PaymentHydrator(), new NullLogger()), new PaymentHydrator(), new NullLogger());
        $context = new Context(new SystemSource());
        $gateway->getPaymentByTransactionId('test', $context);
    }

    public function testCreatePaymentIsSuccessful(): void
    {
        $fakeClient = new FakeClient('mollieTestId', 'paid');
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $transactionService = new FakeTransactionService();

        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new PaymentHydrator(), new NullLogger()), new PaymentHydrator(), new NullLogger());

        $createPayment = new CreatePayment('test','test',new Money(10.00,'EUR'));
        $createPayment->setShopwareOrderNumber('10000');
        $payment = $gateway->createPayment($createPayment,Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame('mollieTestId',$payment->getId());
        $this->assertSame('paid',$payment->getStatus()->value);
    }

    public function testCreatePaymentHandledApiException(): void
    {
        $this->expectException(ApiException::class);
        $fakeClient = new FakeClient();
        $fakeClientFactory = new FakeClientFactory($fakeClient);

        $transactionService = new FakeTransactionService();
        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new PaymentHydrator(), new NullLogger()), new PaymentHydrator(), new NullLogger());

        $createPayment = new CreatePayment('test','test',new Money(10.00,'EUR'));
        $createPayment->setShopwareOrderNumber('10000');
        $gateway->createPayment($createPayment,Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
    }

    public function testGetActivePaymentMethodsReturnsMethodIds(): void
    {
        $body = json_encode([
            '_embedded' => [
                'methods' => [
                    ['id' => 'ideal'],
                    ['id' => 'creditcard'],
                ],
            ],
        ]);
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], (string) $body)]))]);
        $gateway = new MollieGateway(new FakeClientFactory($client), new FakeTransactionService(), new PaymentLinkGateway(new FakeClientFactory($client), new PaymentHydrator(), new NullLogger()), new PaymentHydrator(), new NullLogger());

        $result = $gateway->getActivePaymentMethods(new Money(100.0, 'EUR'), 'DE', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame(['ideal', 'creditcard'], $result);
    }

    public function testGetActivePaymentMethodsReturnsEmptyArrayWhenNoMethods(): void
    {
        $body = json_encode(['_embedded' => ['methods' => []]]);
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], (string) $body)]))]);
        $gateway = new MollieGateway(new FakeClientFactory($client), new FakeTransactionService(), new PaymentLinkGateway(new FakeClientFactory($client), new PaymentHydrator(), new NullLogger()), new PaymentHydrator(), new NullLogger());

        $result = $gateway->getActivePaymentMethods(new Money(100.0, 'EUR'), '', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame([], $result);
    }

    public function testGetActivePaymentMethodsHandlesApiException(): void
    {
        $this->expectException(ApiException::class);

        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(422, [], (string) json_encode(['detail' => 'failed']))]))]);
        $gateway = new MollieGateway(new FakeClientFactory($client), new FakeTransactionService(), new PaymentLinkGateway(new FakeClientFactory($client), new PaymentHydrator(), new NullLogger()), new PaymentHydrator(), new NullLogger());

        $gateway->getActivePaymentMethods(new Money(100.0, 'EUR'), 'DE', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
    }

    public function testUpdatePaymentOnlySendsTheFieldsMollieAcceptsForAnUpdate(): void
    {
        $fakeClient = new FakeClient('tr_test', 'open');
        $gateway = $this->makeGateway($fakeClient);

        $createPayment = new CreatePayment('Order 10000', 'https://shop.example/return', new Money(10.00, 'EUR'));
        $createPayment->setShopwareOrderNumber('10000');
        $createPayment->setWebhookUrl('https://shop.example/webhook');
        $createPayment->setLocale(Locale::deDE);

        $gateway->updatePayment('tr_test', $createPayment, '10000', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $formParams = $fakeClient->getLastPatchOptions()['form_params'];

        $this->assertSame('PATCH', $fakeClient->getLastMethod());
        $this->assertSame('payments/tr_test', $fakeClient->getLastUri());
        $this->assertArrayNotHasKey('amount', $formParams);
        $this->assertArrayNotHasKey('lines', $formParams);
        $this->assertSame('Order 10000', $formParams['description']);
        $this->assertSame('https://shop.example/webhook', $formParams['webhookUrl']);
        $this->assertSame('de_DE', $formParams['locale']);
    }


    public function testCreateOrderPostsThePayloadAndEmbedsThePayments(): void
    {
        $fakeClient = new FakeClient('ord_test', 'paid', embed: true);
        $gateway = $this->makeGateway($fakeClient);

        $order = $gateway->createOrder(self::createOrderPayload(), Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame('ord_test', $order->getId());
        $this->assertSame('POST', $fakeClient->getLastMethod());
        $this->assertSame('orders', $fakeClient->getLastUri());
        $this->assertSame(['embed' => 'payments'], $fakeClient->getLastPostOptions()['query']);
        $this->assertSame('SW10001', $fakeClient->getLastPostOptions()['form_params']['orderNumber']);
    }


    public function testGetOrderEmbedsPaymentsAndRefunds(): void
    {
        $fakeClient = new FakeClient('ord_test', 'paid', embed: true);
        $gateway = $this->makeGateway($fakeClient);

        $order = $gateway->getOrder('ord_test', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame('ord_test', $order->getId());
        $this->assertSame('orders/ord_test', $fakeClient->getLastUri());
        $this->assertSame(['embed' => 'payments,refunds'], $fakeClient->getLastGetOptions()['query']);
    }


    public function testCurrentProfileIsLoadedFromMollie(): void
    {
        $fakeClient = new FakeClient(body: ['id' => 'pfl_1', 'name' => 'Test shop', 'email' => 'shop@example.com']);
        $gateway = $this->makeGateway($fakeClient);

        $profile = $gateway->getCurrentProfile(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame('pfl_1', $profile->getId());
        $this->assertSame('Test shop', $profile->getName());
        $this->assertSame('shop@example.com', $profile->getEmail());
        $this->assertSame('profiles/me', $fakeClient->getLastUri());
    }


    public function testProfileIsLoadedForAGivenApiKey(): void
    {
        $fakeClient = new FakeClient(body: ['id' => 'pfl_1', 'name' => 'Test shop', 'email' => 'shop@example.com']);
        $gateway = $this->makeGateway($fakeClient);

        $profile = $gateway->getProfileForApiKey('test_key');

        $this->assertSame('pfl_1', $profile->getId());
    }


    public function testCustomerIsCreatedWithTheShopwareCustomerNumberAsMetadata(): void
    {
        $fakeClient = new FakeClient(body: ['id' => 'cst_1', 'name' => 'John Doe', 'email' => 'john@example.com', 'metadata' => [], 'locale' => null]);
        $gateway = $this->makeGateway($fakeClient);

        $customer = $gateway->createCustomer(self::shopwareCustomer(), Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $formParams = $fakeClient->getLastPostOptions()['form_params'];

        $this->assertSame('cst_1', $customer->getId());
        $this->assertSame('customers', $fakeClient->getLastUri());
        $this->assertSame('John Doe', $formParams['name']);
        $this->assertSame('john@example.com', $formParams['email']);
        $this->assertSame(['shopwareCustomerNumber' => '10000'], $formParams['metadata']);
        $this->assertArrayNotHasKey('locale', $formParams);
    }

    public function testCustomerLocaleIsDerivedFromTheCustomerLanguage(): void
    {
        $fakeClient = new FakeClient(body: ['id' => 'cst_1', 'name' => 'John Doe', 'email' => 'john@example.com', 'metadata' => [], 'locale' => 'de_DE']);
        $gateway = $this->makeGateway($fakeClient);

        $shopwareCustomer = self::shopwareCustomer();
        $locale = new LocaleEntity();
        $locale->setCode('de-DE');
        $language = new LanguageEntity();
        $language->setLocale($locale);
        $shopwareCustomer->setLanguage($language);

        $gateway->createCustomer($shopwareCustomer, Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame('de_DE', $fakeClient->getLastPostOptions()['form_params']['locale']);
    }


    public function testMandatesAreListedForACustomerPresentScope(): void
    {
        $fakeClient = new FakeClient(body: ['_embedded' => ['mandates' => [
            ['id' => 'mdt_1', 'method' => 'creditcard', 'details' => []],
            ['id' => 'mdt_2', 'method' => 'directdebit', 'details' => []],
        ]]]);
        $gateway = $this->makeGateway($fakeClient);

        $mandates = $gateway->listMandates('cst_1', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertCount(2, $mandates);
        $this->assertSame('mdt_1', $mandates->get('mdt_1')->getId());
        $this->assertSame('customers/cst_1/mandates', $fakeClient->getLastUri());
        $this->assertSame(['scopes' => ['customer-present']], $fakeClient->getLastGetOptions()['query']);
    }


    public function testTerminalsAreListed(): void
    {
        $fakeClient = new FakeClient(body: ['_embedded' => ['terminals' => [[
            'id' => 'term_1',
            'description' => 'Counter 1',
            'currency' => 'EUR',
            'status' => 'active',
            'brand' => 'PAX',
            'model' => 'A920',
            'serialNumber' => null,
        ]]]]);
        $gateway = $this->makeGateway($fakeClient);

        $terminals = $gateway->listTerminals(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertCount(1, $terminals);
        $this->assertSame('terminals', $fakeClient->getLastUri());
    }


    public function testMandateIsRevoked(): void
    {
        $fakeClient = new FakeClient(body: []);
        $gateway = $this->makeGateway($fakeClient);

        $revoked = $gateway->revokeMandate('cst_1', 'mdt_1', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertTrue($revoked);
        $this->assertSame('DELETE', $fakeClient->getLastMethod());
        $this->assertSame('customers/cst_1/mandates/mdt_1', $fakeClient->getLastUri());
    }


    public function testPaymentIsLoadedWithItsRefundsAndCaptures(): void
    {
        $fakeClient = new FakeClient('tr_test', 'paid');
        $gateway = $this->makeGateway($fakeClient);

        $payment = $gateway->getPayment('tr_test', '10000', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame('tr_test', $payment->getId());
        $this->assertSame('payments/tr_test', $fakeClient->getLastUri());
        $this->assertSame(['embed' => 'refunds,captures'], $fakeClient->getLastGetOptions()['query']);
    }

    public function testPaymentIsCancelled(): void
    {
        $fakeClient = new FakeClient('tr_test', 'canceled');
        $gateway = $this->makeGateway($fakeClient);

        $payment = $gateway->cancelPayment('tr_test', '10000', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame('canceled', $payment->getStatus()->value);
        $this->assertSame('DELETE', $fakeClient->getLastMethod());
        $this->assertSame('payments/tr_test', $fakeClient->getLastUri());
    }


    public function testOrderIsCancelled(): void
    {
        $fakeClient = new FakeClient('ord_test', 'canceled', embed: true);
        $gateway = $this->makeGateway($fakeClient);

        $order = $gateway->cancelOrder('ord_test', '10000', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame('ord_test', $order->getId());
        $this->assertSame('DELETE', $fakeClient->getLastMethod());
        $this->assertSame('orders/ord_test', $fakeClient->getLastUri());
    }


    public function testSubscriptionPaymentsAreListed(): void
    {
        $fakeClient = new FakeClient(body: ['_embedded' => ['payments' => [
            ['id' => 'tr_1', 'status' => 'paid'],
            ['id' => 'tr_2', 'status' => 'failed'],
        ]]]);
        $gateway = $this->makeGateway($fakeClient);

        $payments = $gateway->listSubscriptionPayments('cst_1', 'sub_1', '10000', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertCount(2, $payments);
        $this->assertSame('tr_1', $payments->get('tr_1')->getId());
        $this->assertSame('customers/cst_1/subscriptions/sub_1/payments', $fakeClient->getLastUri());
    }

    public function testSubscriptionPaymentsAreEmptyWhenMollieReturnsNone(): void
    {
        $gateway = $this->makeGateway(new FakeClient(body: []));

        $payments = $gateway->listSubscriptionPayments('cst_1', 'sub_1', '10000', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertCount(0, $payments);
    }


    public function testCaptureIsCreatedForAPayment(): void
    {
        $fakeClient = new FakeClient(body: ['id' => 'cpt_1', 'status' => 'pending', 'amount' => ['value' => '20.00', 'currency' => 'EUR']]);
        $gateway = $this->makeGateway($fakeClient);

        $capture = $gateway->createCapture(self::createCapturePayload(), 'tr_test', '10000', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame('cpt_1', $capture->getId());
        $this->assertSame('payments/tr_test/captures', $fakeClient->getLastUri());
        $this->assertSame(['value' => '20.00', 'currency' => 'EUR'], $fakeClient->getLastPostOptions()['form_params']['amount']);
    }


    public function testShipmentIsCreatedForAnOrder(): void
    {
        $fakeClient = new FakeClient(body: ['id' => 'shp_1']);
        $gateway = $this->makeGateway($fakeClient);

        $lines = new ShippingItemCollection();
        $lines->add(new ShippingItem(2, 20.00, 'odl_1'));

        $shipment = $gateway->createShipment(new CreateShipment($lines), 'ord_test', '10000', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame('shp_1', $shipment->getId());
        $this->assertSame('orders/ord_test/shipments', $fakeClient->getLastUri());
        $this->assertSame([['id' => 'odl_1', 'quantity' => 2]], $fakeClient->getLastPostOptions()['form_params']['lines']);
    }


    public function testOrderLinesAreCancelled(): void
    {
        $fakeClient = new FakeClient(body: []);
        $gateway = $this->makeGateway($fakeClient);

        $gateway->cancelOrderLines('ord_test', 'odl_1', 2, '10000', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame('DELETE', $fakeClient->getLastMethod());
        $this->assertSame('orders/ord_test/lines', $fakeClient->getLastUri());
        $this->assertSame(['lines' => [['id' => 'odl_1', 'quantity' => 2]]], $fakeClient->getLastDeleteOptions()['json']);
    }


    public function testAuthorizationIsReleased(): void
    {
        $fakeClient = new FakeClient(body: []);
        $gateway = $this->makeGateway($fakeClient);

        $gateway->releaseAuthorization('tr_test', '10000', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame('POST', $fakeClient->getLastMethod());
        $this->assertSame('payments/tr_test/release-authorization', $fakeClient->getLastUri());
    }


    public function testLegacyTransactionIsRepairedFromTheTransactionCustomFields(): void
    {
        $fakeClient = new FakeClient('mollieTestId', 'paid', embed: true);
        $transactionService = new FakeTransactionService();
        $transactionService->withOrderCustomFields(['transactionReturnUrl' => 'payment/finalize']);
        $transactionService->withTransactionCustomFields(['order_id' => 'ord_legacy']);
        $gateway = $this->makeGateway($fakeClient, $transactionService);

        $payment = $gateway->getPaymentByTransactionId('test', new Context(new SystemSource()));

        $this->assertSame('ord_legacy', $payment->getOrderId());
        $this->assertSame('payment/finalize', $payment->getFinalizeUrl());
    }

    public function testRepairedPaymentIsPersistedOnTheTransaction(): void
    {
        $fakeClient = new FakeClient('mollieTestId', 'paid', embed: true);
        $transactionService = new FakeTransactionService();
        $transactionService->withOrderCustomFields([
            'order_id' => 'ord_legacy',
            'transactionReturnUrl' => 'payment/finalize',
        ]);
        $gateway = $this->makeGateway($fakeClient, $transactionService);

        $gateway->getPaymentByTransactionId('test', new Context(new SystemSource()));

        $this->assertCount(1, $transactionService->getSavedPaymentExtensions());
    }

    private function makeGateway(FakeClient $client, ?FakeTransactionService $transactionService = null): MollieGateway
    {
        $clientFactory = new FakeClientFactory($client);

        return new MollieGateway(
            $clientFactory,
            $transactionService ?? new FakeTransactionService(),
            new PaymentLinkGateway($clientFactory, new PaymentHydrator(), new NullLogger()),
            new PaymentHydrator(),
            new NullLogger()
        );
    }

    /**
     * A Mollie 4xx/5xx is funnelled through convertException(), which turns the response body into
     * an ApiException. One dataset per gateway call, so a missing try/catch is visible. createPayment
     * and getActivePaymentMethods keep their own tests above.
     *
     * @param callable(MollieGateway): mixed $call
     */
    #[DataProvider('everyGatewayCall')]
    public function testAMollieErrorBecomesAnApiException(callable $call): void
    {
        $gateway = $this->makeGateway(new FakeClient());

        try {
            $call($gateway);
            $this->fail('gateway call did not throw');
        } catch (ApiException $exception) {
            $this->assertSame('Failed Response', $exception->getTitle());
            $this->assertSame('This response failed and simulate an exception', $exception->getDetails());
            $this->assertSame('payment.id', $exception->getField());
        }
    }

    /**
     * @return iterable<string, array{callable(MollieGateway): mixed}>
     */
    public static function everyGatewayCall(): iterable
    {
        $salesChannelId = Defaults::SALES_CHANNEL_TYPE_STOREFRONT;

        yield 'updatePayment' => [fn (MollieGateway $gateway) => $gateway->updatePayment('tr_test', new CreatePayment('test', 'test', new Money(10.00, 'EUR')), '10000', $salesChannelId)];
        yield 'createOrder' => [fn (MollieGateway $gateway) => $gateway->createOrder(self::createOrderPayload(), $salesChannelId)];
        yield 'getOrder' => [fn (MollieGateway $gateway) => $gateway->getOrder('ord_test', $salesChannelId)];
        yield 'getCurrentProfile' => [fn (MollieGateway $gateway) => $gateway->getCurrentProfile($salesChannelId)];
        yield 'getProfileForApiKey' => [fn (MollieGateway $gateway) => $gateway->getProfileForApiKey('test_key')];
        yield 'createCustomer' => [fn (MollieGateway $gateway) => $gateway->createCustomer(self::shopwareCustomer(), $salesChannelId)];
        yield 'listMandates' => [fn (MollieGateway $gateway) => $gateway->listMandates('cst_1', $salesChannelId)];
        yield 'listTerminals' => [fn (MollieGateway $gateway) => $gateway->listTerminals($salesChannelId)];
        yield 'revokeMandate' => [fn (MollieGateway $gateway) => $gateway->revokeMandate('cst_1', 'mdt_1', $salesChannelId)];
        yield 'getPayment' => [fn (MollieGateway $gateway) => $gateway->getPayment('tr_test', '10000', $salesChannelId)];
        yield 'cancelPayment' => [fn (MollieGateway $gateway) => $gateway->cancelPayment('tr_test', '10000', $salesChannelId)];
        yield 'cancelOrder' => [fn (MollieGateway $gateway) => $gateway->cancelOrder('ord_test', '10000', $salesChannelId)];
        yield 'listSubscriptionPayments' => [fn (MollieGateway $gateway) => $gateway->listSubscriptionPayments('cst_1', 'sub_1', '10000', $salesChannelId)];
        yield 'createCapture' => [fn (MollieGateway $gateway) => $gateway->createCapture(self::createCapturePayload(), 'tr_test', '10000', $salesChannelId)];
        yield 'createShipment' => [fn (MollieGateway $gateway) => $gateway->createShipment(new CreateShipment(new ShippingItemCollection()), 'ord_test', '10000', $salesChannelId)];
        yield 'cancelOrderLines' => [fn (MollieGateway $gateway) => $gateway->cancelOrderLines('ord_test', 'odl_1', 2, '10000', $salesChannelId)];
        yield 'releaseAuthorization' => [fn (MollieGateway $gateway) => $gateway->releaseAuthorization('tr_test', '10000', $salesChannelId)];
    }

    private static function createOrderPayload(): CreateOrder
    {
        return new CreateOrder(
            'SW10001',
            'https://shop.example/return',
            new Money(119.00, 'EUR'),
            new LineItemCollection(),
            new Address('john@example.com', 'Mr.', 'John', 'Doe', 'Main Street 1', '12345', 'Berlin', 'DE'),
            Locale::deDE
        );
    }

    private static function createCapturePayload(): CreateCapture
    {
        $items = new ShippingItemCollection();
        $items->add(new ShippingItem(2, 20.00, 'odl_1'));

        return new CreateCapture($items, 'EUR', 'Capture for order 10000');
    }

    private static function shopwareCustomer(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setFirstName('John');
        $customer->setLastName('Doe');
        $customer->setEmail('john@example.com');
        $customer->setCustomerNumber('10000');

        return $customer;
    }
}
