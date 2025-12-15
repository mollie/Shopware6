<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CaptureMode;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Action\Finalize;
use Mollie\Shopware\Component\Payment\Action\Pay;
use Mollie\Shopware\Component\Payment\CreatePaymentBuilder;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Unit\Fake\FakeEventDispatcher;
use Mollie\Shopware\Unit\Logger\FakeSettingsService;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeBankTransferAwarePaymentHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeCustomerRepository;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Payment\Fake\FakeManualCaptureModeAwarePaymentHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeOrderTransactionStateHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeRecurringAwarePaymentHandler;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

#[CoversClass(CreatePaymentBuilder::class)]
final class CreatePaymentBuilderTest extends TestCase
{
    public function testBuild(): void
    {
        $fixtures = $this->createTestFixtures();
        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $builder = $this->createBuilder($fixtures, $paymentSettings);
        $fakePaymentHandler = new FakePaymentMethodHandler();
        $requestDataBag = new RequestDataBag();

        $fakeTransactionDataLoader = new FakeTransactionService();
        $transactionData = $this->getTransactionData($fixtures, $fakeTransactionDataLoader);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);
        $actual->setCardToken('testCard');
        $actual->setMethod(PaymentMethod::PAYPAL);

        $this->assertInstanceOf(CreatePayment::class, $actual);

        $expected = [
            'description' => 'test_10000-100',
            'amount' => [
                'currency' => 'EUR',
                'value' => '100.00',
            ],
            'redirectUrl' => '',
            'cancelUrl' => '',
            'webhookUrl' => '',
            'method' => 'paypal',
            'billingAddress' => [
                'title' => 'Not specified',
                'givenName' => 'Tester',
                'familyName' => 'Test',
                'streetAndNumber' => 'Test Street',
                'postalCode' => '12345',
                'email' => 'fake@unit.test',
                'city' => 'Test City',
                'country' => 'DE',
            ],
            'shippingAddress' => [
                'title' => 'Not specified',
                'givenName' => 'Tester',
                'familyName' => 'Test',
                'streetAndNumber' => 'Test Street',
                'postalCode' => '12345',
                'email' => 'fake@unit.test',
                'city' => 'Test City',
                'country' => 'DE',
            ],
            'locale' => 'en_GB',
            'lines' => [
                [
                    'type' => 'digital',
                    'vatRate' => '19',
                    'vatAmount' => [
                        'currency' => 'EUR',
                        'value' => '2.09',
                    ],
                    'sku' => 'SW1000',
                    'description' => 'Fake product',
                    'quantity' => 1,
                    'unitPrice' => [
                        'currency' => 'EUR',
                        'value' => '10.99',
                    ],
                    'totalAmount' => [
                        'currency' => 'EUR',
                        'value' => '10.99',
                    ]
                ],
                [
                    'type' => 'shipping_fee',
                    'vatRate' => '19',
                    'vatAmount' => [
                        'currency' => 'EUR',
                        'value' => '0.95',
                    ],
                    'sku' => 'mol-delivery-fake-shipping-method-id',
                    'description' => 'DHL',
                    'quantity' => 1,
                    'unitPrice' => [
                        'currency' => 'EUR',
                        'value' => '4.99',
                    ],
                    'totalAmount' => [
                        'currency' => 'EUR',
                        'value' => '4.99',
                    ],
                ],
            ],
            'sequenceType' => 'oneoff',
            'cardToken' => 'testCard',
            'metadata' => [
                'shopwareOrderNumber' => '10000'
            ]
        ];
        $this->assertEquals($expected, $actual->toArray());

        $this->assertSame($expected['cardToken'], $actual->getCardToken());
        $this->assertSame($expected['description'], $actual->getDescription());
        $this->assertInstanceOf(Address::class, $actual->getShippingAddress());
        $this->assertInstanceOf(Address::class, $actual->getBillingAddress());
        $this->assertInstanceOf(LineItemCollection::class, $actual->getLines());
        $this->assertEquals(new Money(100.00, 'EUR'), $actual->getAmount());
        $this->assertSame($expected['method'], $actual->getMethod()->value);
        $this->assertEquals($expected['locale'],$actual->getLocale()->value);
        $this->assertSame($expected['webhookUrl'], $actual->getWebhookUrl());
        $this->assertSame($expected['redirectUrl'], $actual->getRedirectUrl());
        $this->assertSame($expected['sequenceType'], $actual->getSequenceType()->value);

        $this->assertSame($expected['metadata']['shopwareOrderNumber'], $actual->getShopwareOrderNumber());
    }

    public function testSetters(): void
    {
        $createPayment = new CreatePayment('test', '', new Money(10.00, 'EUR'));
        $createPayment->setDescription('test2');

        $this->assertSame('test2', $createPayment->getDescription());
    }

    public function testBuildWithManualCaptureModeAwareHandler(): void
    {
        $fixtures = $this->createTestFixtures();
        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $builder = $this->createBuilder($fixtures, $paymentSettings);
        $requestDataBag = new RequestDataBag();

        $transactionService = new FakeTransactionService();
        $eventDispatcher = new FakeEventDispatcher();
        $fakeOrderTransactionStateHandler = new FakeOrderTransactionStateHandler();
        $pay = new Pay($transactionService, $builder, $fixtures->gateway, $fakeOrderTransactionStateHandler, $fixtures->fakeRouteBuilder, $eventDispatcher, $fixtures->logger);
        $finalize = new Finalize($transactionService, $fixtures->gateway, $eventDispatcher, $fixtures->logger);
        $fakePaymentHandler = new FakeManualCaptureModeAwarePaymentHandler($pay, $finalize, $fixtures->logger);

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame(CaptureMode::MANUAL->value, $actual->getCaptureMode()->value);
    }

    public function testBuildWithBankTransferAwareHandler(): void
    {
        $fixtures = $this->createTestFixtures();
        $dueDateDays = 14;
        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', $dueDateDays);
        $builder = $this->createBuilder($fixtures, $paymentSettings);
        $requestDataBag = new RequestDataBag();

        $transactionService = new FakeTransactionService();
        $pay = new Pay($transactionService, $builder, $fixtures->gateway, new FakeOrderTransactionStateHandler(), $fixtures->fakeRouteBuilder, new FakeEventDispatcher(), $fixtures->logger);
        $finalize = new Finalize($transactionService, $fixtures->gateway, new FakeEventDispatcher(), $fixtures->logger);
        $fakePaymentHandler = new FakeBankTransferAwarePaymentHandler($pay, $finalize, $fixtures->logger);

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertNotNull($actual->getDueDate());
        $this->assertInstanceOf(\DateTime::class, $actual->getDueDate());

        $expectedDueDate = new \DateTime('now', new \DateTimeZone('UTC'));
        $expectedDueDate->modify('+' . $dueDateDays . ' days');
        $this->assertSame($expectedDueDate->format('Y-m-d'), $actual->getDueDate()->format('Y-m-d'));
    }

    public function testBuildWithBankTransferAwareHandlerButNoDueDateDays(): void
    {
        $fixtures = $this->createTestFixtures();
        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $builder = $this->createBuilder($fixtures, $paymentSettings);
        $requestDataBag = new RequestDataBag();

        $transactionService = new FakeTransactionService();
        $pay = new Pay($transactionService, $builder, $fixtures->gateway, new FakeOrderTransactionStateHandler(), $fixtures->fakeRouteBuilder, new FakeEventDispatcher(), $fixtures->logger);
        $finalize = new Finalize($transactionService, $fixtures->gateway, new FakeEventDispatcher(), $fixtures->logger);
        $fakePaymentHandler = new FakeBankTransferAwarePaymentHandler($pay, $finalize, $fixtures->logger);

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertNull($actual->getDueDate());
    }

    public function testBuildWithMollieCustomerId(): void
    {
        $fixtures = $this->createTestFixtures();
        $mollieCustomerId = 'cust_test_mollie_id';
        $profileId = 'pfl_test_profile';

        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings, profileId: $profileId);
        $builder = new CreatePaymentBuilder($fixtures->fakeRouteBuilder, $settingsService, $fixtures->gateway, $fixtures->fakeCustomerRepository, $fixtures->logger);
        $requestDataBag = new RequestDataBag();

        $transactionService = new FakeTransactionService();
        $transactionService->withMollieCustomerId($profileId, $mollieCustomerId);
        $fakePaymentHandler = new FakePaymentMethodHandler();

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame($mollieCustomerId, $actual->getCustomerId());
    }

    public function testBuildWithoutMollieCustomerId(): void
    {
        $fixtures = $this->createTestFixtures();
        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $builder = $this->createBuilder($fixtures, $paymentSettings);
        $requestDataBag = new RequestDataBag();

        $transactionService = new FakeTransactionService();
        $fakePaymentHandler = new FakePaymentMethodHandler();

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertNull($actual->getCustomerId());
    }

    public function testBuildSetsSequenceTypeFirstWhenNotGuestAndSavePaymentDetails(): void
    {
        $fixtures = $this->createTestFixtures();
        $mollieCustomerId = 'cust_test_mollie_id';
        $profileId = 'pfl_test_profile';

        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings, profileId: $profileId);
        $builder = new CreatePaymentBuilder($fixtures->fakeRouteBuilder, $settingsService, $fixtures->gateway, $fixtures->fakeCustomerRepository, $fixtures->logger);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('savePaymentDetails', true);

        $transactionService = new FakeTransactionService();
        $transactionService->withMollieCustomerId($profileId, $mollieCustomerId);
        $fakePaymentHandler = new FakePaymentMethodHandler();

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('first', $actual->getSequenceType()->value);
    }

    public function testBuildKeepsSequenceTypeOneoffWhenGuestEvenIfSavePaymentDetails(): void
    {
        $fixtures = $this->createTestFixtures();
        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $builder = $this->createBuilder($fixtures, $paymentSettings);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('savePaymentDetails', true);

        $transactionService = new FakeTransactionService();
        $fakePaymentHandler = new FakePaymentMethodHandler();

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $transactionData->getCustomer()->setGuest(true);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('oneoff', $actual->getSequenceType()->value);
    }

    public function testBuildSetsSequenceTypeRecurringWhenAllConditionsMet(): void
    {
        $fixtures = $this->createTestFixtures();
        $mollieCustomerId = 'cust_test_mollie_id';
        $profileId = 'pfl_test_profile';
        $mandateId = 'tr_test_mandate_id';

        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings, profileId: $profileId);
        $builder = new CreatePaymentBuilder($fixtures->fakeRouteBuilder, $settingsService, $fixtures->gateway, $fixtures->fakeCustomerRepository, $fixtures->logger);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('mandateId', $mandateId);

        $transactionService = new FakeTransactionService();
        $transactionService->withMollieCustomerId($profileId, $mollieCustomerId);

        $pay = new Pay($transactionService, $builder, $fixtures->gateway, new FakeOrderTransactionStateHandler(), $fixtures->fakeRouteBuilder, new FakeEventDispatcher(), $fixtures->logger);
        $finalize = new Finalize($transactionService, $fixtures->gateway, new FakeEventDispatcher(), $fixtures->logger);
        $fakePaymentHandler = new FakeRecurringAwarePaymentHandler($pay, $finalize, $fixtures->logger);

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('recurring', $actual->getSequenceType()->value);
        $this->assertSame($mandateId, $actual->getMandateId());
    }

    public function testBuildKeepsSequenceTypeFirstWhenMandateIdMissingEvenIfRecurringHandler(): void
    {
        $fixtures = $this->createTestFixtures();
        $mollieCustomerId = 'cust_test_mollie_id';
        $profileId = 'pfl_test_profile';

        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings, profileId: $profileId);
        $builder = new CreatePaymentBuilder($fixtures->fakeRouteBuilder, $settingsService, $fixtures->gateway, $fixtures->fakeCustomerRepository, $fixtures->logger);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('savePaymentDetails', true);

        $transactionService = new FakeTransactionService();
        $transactionService->withMollieCustomerId($profileId, $mollieCustomerId);

        $pay = new Pay($transactionService, $builder, $fixtures->gateway, new FakeOrderTransactionStateHandler(), $fixtures->fakeRouteBuilder, new FakeEventDispatcher(), $fixtures->logger);
        $finalize = new Finalize($transactionService, $fixtures->gateway, new FakeEventDispatcher(), $fixtures->logger);
        $fakePaymentHandler = new FakeRecurringAwarePaymentHandler($pay, $finalize, $fixtures->logger);

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('first', $actual->getSequenceType()->value);
        $this->assertNull($actual->getMandateId());
    }

    public function testBuildKeepsSequenceTypeOneoffWhenNotRecurringAwareHandler(): void
    {
        $fixtures = $this->createTestFixtures();
        $mollieCustomerId = 'cust_test_mollie_id';
        $profileId = 'pfl_test_profile';
        $mandateId = 'tr_test_mandate_id';

        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings, profileId: $profileId);
        $builder = new CreatePaymentBuilder($fixtures->fakeRouteBuilder, $settingsService, $fixtures->gateway, $fixtures->fakeCustomerRepository, $fixtures->logger);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('mandateId', $mandateId);

        $transactionService = new FakeTransactionService();
        $transactionService->withMollieCustomerId($profileId, $mollieCustomerId);
        $fakePaymentHandler = new FakePaymentMethodHandler();

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('oneoff', $actual->getSequenceType()->value);
        $this->assertNull($actual->getMandateId());
    }

    public function testBuildCreatesCustomerWhenSequenceTypeIsFirstAndNoCustomerId(): void
    {
        $fixtures = $this->createTestFixtures();
        $profileId = 'pfl_test_profile';

        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings, profileId: $profileId);
        $builder = new CreatePaymentBuilder($fixtures->fakeRouteBuilder, $settingsService, $fixtures->gateway, $fixtures->fakeCustomerRepository, $fixtures->logger);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('savePaymentDetails', true);

        $transactionService = new FakeTransactionService();
        $fakePaymentHandler = new FakePaymentMethodHandler();

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('first', $actual->getSequenceType()->value);
        $this->assertNotNull($actual->getCustomerId());
        $this->assertStringStartsWith('cust_fake_', $actual->getCustomerId());
    }

    public function testBuildDoesNotCreateCustomerWhenSequenceTypeIsOneoff(): void
    {
        $fixtures = $this->createTestFixtures();
        $profileId = 'pfl_test_profile';

        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings, profileId: $profileId);
        $builder = new CreatePaymentBuilder($fixtures->fakeRouteBuilder, $settingsService, $fixtures->gateway, $fixtures->fakeCustomerRepository, $fixtures->logger);
        $requestDataBag = new RequestDataBag();

        $transactionService = new FakeTransactionService();
        $fakePaymentHandler = new FakePaymentMethodHandler();

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('oneoff', $actual->getSequenceType()->value);
        $this->assertNull($actual->getCustomerId());
    }

    public function testBuildDoesNotCreateCustomerWhenCustomerIdAlreadyExists(): void
    {
        $fixtures = $this->createTestFixtures();
        $mollieCustomerId = 'cust_existing_mollie_id';
        $profileId = 'pfl_test_profile';

        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings, profileId: $profileId);
        $builder = new CreatePaymentBuilder($fixtures->fakeRouteBuilder, $settingsService, $fixtures->gateway, $fixtures->fakeCustomerRepository, $fixtures->logger);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('savePaymentDetails', true);

        $transactionService = new FakeTransactionService();
        $transactionService->withMollieCustomerId($profileId, $mollieCustomerId);
        $fakePaymentHandler = new FakePaymentMethodHandler();

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('first', $actual->getSequenceType()->value);
        $this->assertSame($mollieCustomerId, $actual->getCustomerId());
    }

    public function testBuildDoesNotCreateCustomerWhenGuest(): void
    {
        $fixtures = $this->createTestFixtures();
        $profileId = 'pfl_test_profile';

        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings, profileId: $profileId);
        $builder = new CreatePaymentBuilder($fixtures->fakeRouteBuilder, $settingsService, $fixtures->gateway, $fixtures->fakeCustomerRepository, $fixtures->logger);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('savePaymentDetails', true);

        $transactionService = new FakeTransactionService();
        $fakePaymentHandler = new FakePaymentMethodHandler();

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $transactionData->getCustomer()->setGuest(true);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('oneoff', $actual->getSequenceType()->value);
        $this->assertNull($actual->getCustomerId());
    }

    public function testBuildWithEmptyOrderNumberFormat(): void
    {
        $fixtures = $this->createTestFixtures();
        $paymentSettings = new PaymentSettings('', 0);
        $builder = $this->createBuilder($fixtures, $paymentSettings);
        $fakePaymentHandler = new FakePaymentMethodHandler();
        $requestDataBag = new RequestDataBag();

        $transactionService = new FakeTransactionService();
        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('10000', $actual->getDescription());
    }

    public function testBuildWithEmptyProfileId(): void
    {
        $fixtures = $this->createTestFixtures();
        $profileId = 'fake_profile';
        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings, profileId: '');
        $builder = new CreatePaymentBuilder($fixtures->fakeRouteBuilder, $settingsService, $fixtures->gateway, $fixtures->fakeCustomerRepository, $fixtures->logger);
        $requestDataBag = new RequestDataBag();

        $transactionService = new FakeTransactionService();
        $transactionService->withMollieCustomerId($profileId, 'cust_from_fallback_profile');
        $fakePaymentHandler = new FakePaymentMethodHandler();

        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('cust_from_fallback_profile', $actual->getCustomerId());
    }

    public function testBuildWithNullLineItems(): void
    {
        $fixtures = $this->createTestFixtures();
        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $builder = $this->createBuilder($fixtures, $paymentSettings);
        $fakePaymentHandler = new FakePaymentMethodHandler();
        $requestDataBag = new RequestDataBag();

        $transactionService = new FakeTransactionService();
        $transactionService->withNullLineItems();
        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertInstanceOf(LineItemCollection::class, $actual->getLines());
    }

    public function testBuildWithZeroShippingCosts(): void
    {
        $fixtures = $this->createTestFixtures();
        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $builder = $this->createBuilder($fixtures, $paymentSettings);
        $fakePaymentHandler = new FakePaymentMethodHandler();
        $requestDataBag = new RequestDataBag();

        $transactionService = new FakeTransactionService();
        $transactionService->withZeroShippingCosts();
        $transactionData = $this->getTransactionData($fixtures, $transactionService);
        $actual = $builder->build($transactionData, $fakePaymentHandler, $requestDataBag, $fixtures->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $lines = $actual->getLines();
        foreach ($lines as $line) {
            $this->assertNotSame('shipping_fee', $line->getType()->value);
        }
    }

    private function createTestFixtures(): TestFixtures
    {
        return new TestFixtures(
            fakeRouteBuilder: new FakeRouteBuilder(),
            fakeCustomerRepository: new FakeCustomerRepository(),
            gateway: new FakeGateway('test'),
            logger: new NullLogger(),
            context: new Context(new SystemSource()),
        );
    }

    private function createBuilder(TestFixtures $fixtures, PaymentSettings $paymentSettings): CreatePaymentBuilder
    {
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings);

        return new CreatePaymentBuilder($fixtures->fakeRouteBuilder, $settingsService, $fixtures->gateway, $fixtures->fakeCustomerRepository, $fixtures->logger);
    }

    private function getTransactionData(TestFixtures $fixtures, FakeTransactionService $transactionService): \Mollie\Shopware\Component\Transaction\TransactionDataStruct
    {
        return $transactionService->findById('test', $fixtures->context);
    }
}

/**
 * @internal Test fixture holder
 */
final class TestFixtures
{
    public function __construct(
        public readonly FakeRouteBuilder $fakeRouteBuilder,
        public readonly FakeCustomerRepository $fakeCustomerRepository,
        public readonly FakeGateway $gateway,
        public readonly NullLogger $logger,
        public readonly Context $context,
    ) {
    }
}
