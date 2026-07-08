<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CaptureMode;
use Mollie\Shopware\Component\Mollie\CreateOrder;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\LineItemFilter;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\RoundingDifferenceFixer;
use Mollie\Shopware\Component\Payment\PayloadBuilder;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Unit\Fake\FakeCustomerRepository;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeBankTransferAwarePaymentHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Payment\Fake\FakeManualCaptureModeAwarePaymentHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeOrdersApiAwarePaymentHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakePhoneAwarePaymentMethodHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeRecurringAwarePaymentHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeSubscriptionAwarePaymentHandler;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

#[CoversClass(PayloadBuilder::class)]
final class PayloadBuilderTest extends TestCase
{
    private Context $context;

    public function setUp(): void
    {
        $this->context = new Context(new SystemSource());
    }

    public function testBuild(): void
    {
        $builder = $this->createBuilder();

        $transactionData = (new FakeTransactionService())->findById('test', $this->context);

        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);
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
                    // the fixture tax (19% of the gross 10.99) breaks the vatAmount invariant
                    // Mollie enforces, so it is derived from the totalAmount: 10.99 * 19 / 119
                    'vatAmount' => [
                        'currency' => 'EUR',
                        'value' => '1.75',
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
                    // derived like above: 4.99 * 19 / 119
                    'vatAmount' => [
                        'currency' => 'EUR',
                        'value' => '0.80',
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
            ],
            'storeCredentials' => false
        ];
        $this->assertEquals($expected, $actual->toArray());

        $this->assertSame($expected['cardToken'], $actual->getCardToken());
        $this->assertSame($expected['description'], $actual->getDescription());
        $this->assertInstanceOf(Address::class, $actual->getShippingAddress());
        $this->assertInstanceOf(Address::class, $actual->getBillingAddress());
        $this->assertInstanceOf(LineItemCollection::class, $actual->getLines());
        $this->assertEquals(new Money(100.00, 'EUR'), $actual->getAmount());
        $this->assertSame($expected['method'], $actual->getMethod()->value);
        $this->assertEquals($expected['locale'], $actual->getLocale()->value);
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
        $builder = $this->createBuilder();

        $transactionData = (new FakeTransactionService())->findById('test', $this->context);
        $actual = $builder->buildPayment($transactionData, new FakeManualCaptureModeAwarePaymentHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame(CaptureMode::MANUAL->value, $actual->getCaptureMode()->value);
    }

    public function testBuildWithBankTransferAwareHandler(): void
    {
        $dueDateDays = 14;
        $expectedDueDate = new \DateTime('now', new \DateTimeZone('UTC'));
        $expectedDueDate->modify('+' . $dueDateDays . ' days');

        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', $dueDateDays);
        $builder = $this->createBuilder($paymentSettings);

        $transactionData = (new FakeTransactionService())->findById('test', $this->context);
        $actual = $builder->buildPayment($transactionData, new FakeBankTransferAwarePaymentHandler(), new RequestDataBag(), $this->context);

        $actualArray = $actual->toArray();

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertNotNull($actual->getDueDate());
        $this->assertInstanceOf(\DateTime::class, $actual->getDueDate());
        $this->assertSame($expectedDueDate->format('Y-m-d'),$actualArray['dueDate']);

        $this->assertSame($expectedDueDate->format('Y-m-d'), $actual->getDueDate()->format('Y-m-d'));
    }

    public function testBuildWithBankTransferAwareHandlerButNoDueDateDays(): void
    {
        $builder = $this->createBuilder();

        $transactionData = (new FakeTransactionService())->findById('test', $this->context);
        $actual = $builder->buildPayment($transactionData, new FakeBankTransferAwarePaymentHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertNull($actual->getDueDate());
    }

    public function testBuildWithMollieCustomerId(): void
    {
        $mollieCustomerId = 'cust_test_mollie_id';
        $profileId = 'pfl_test_profile';

        $builder = $this->createBuilder(profileId: $profileId);

        $transactionService = new FakeTransactionService();
        $transactionService->withMollieCustomerId($profileId, $mollieCustomerId);
        $transactionData = $transactionService->findById('test', $this->context);

        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame($mollieCustomerId, $actual->getCustomerId());
    }

    public function testBuildWithoutMollieCustomerId(): void
    {
        $builder = $this->createBuilder();

        $transactionData = (new FakeTransactionService())->findById('test', $this->context);
        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertNull($actual->getCustomerId());
    }

    public function testBuildSetsSequenceTypeFirstWhenNotGuestAndSavePaymentDetails(): void
    {
        $mollieCustomerId = 'cust_test_mollie_id';
        $profileId = 'pfl_test_profile';

        $builder = $this->createBuilder(profileId: $profileId);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('savePaymentDetails', true);

        $transactionService = new FakeTransactionService();
        $transactionService->withMollieCustomerId($profileId, $mollieCustomerId);

        $transactionData = $transactionService->findById('test', $this->context);
        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), $requestDataBag, $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('first', $actual->getSequenceType()->value);
    }

    public function testBuildKeepsSequenceTypeOneoffWhenGuestEvenIfSavePaymentDetails(): void
    {
        $builder = $this->createBuilder();

        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('savePaymentDetails', true);

        $transactionData = (new FakeTransactionService())->findById('test', $this->context);
        $transactionData->getCustomer()->setGuest(true);

        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), $requestDataBag, $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('oneoff', $actual->getSequenceType()->value);
    }

    public function testBuildSetsSequenceTypeRecurringWhenAllConditionsMet(): void
    {
        $mollieCustomerId = 'cust_test_mollie_id';
        $profileId = 'pfl_test_profile';
        $mandateId = 'tr_test_mandate_id';

        $builder = $this->createBuilder(profileId: $profileId);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('mandateId', $mandateId);

        $transactionService = new FakeTransactionService();
        $transactionService->withMollieCustomerId($profileId, $mollieCustomerId);

        $transactionData = $transactionService->findById('test', $this->context);

        $actual = $builder->buildPayment($transactionData, new FakeRecurringAwarePaymentHandler(), $requestDataBag, $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('recurring', $actual->getSequenceType()->value);
        $this->assertSame($mandateId, $actual->getMandateId());
    }

    public function testBuildKeepsSequenceTypeFirstWhenMandateIdMissingEvenIfRecurringHandler(): void
    {
        $mollieCustomerId = 'cust_test_mollie_id';
        $profileId = 'pfl_test_profile';

        $builder = $this->createBuilder(profileId: $profileId);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('savePaymentDetails', true);

        $transactionService = new FakeTransactionService();
        $transactionService->withMollieCustomerId($profileId, $mollieCustomerId);

        $transactionData = $transactionService->findById('test', $this->context);
        $actual = $builder->buildPayment($transactionData, new FakeRecurringAwarePaymentHandler(), $requestDataBag, $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('first', $actual->getSequenceType()->value);
        $this->assertNull($actual->getMandateId());
    }

    public function testBuildSetsSequenceTypeFirstForSubscriptionLineItemWhenSubscriptionsEnabled(): void
    {
        $profileId = 'pfl_test_profile';

        $subscriptionSettings = new SubscriptionSettings(enabled: true);
        $builder = $this->createBuilder(profileId: $profileId, subscriptionSettings: $subscriptionSettings);

        $transactionService = new FakeTransactionService();
        $transactionService->withSubscriptionLineItem();
        $transactionData = $transactionService->findById('test', $this->context);

        $actual = $builder->buildPayment($transactionData, new FakeSubscriptionAwarePaymentHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('first', $actual->getSequenceType()->value);
    }

    public function testBuildKeepsSequenceTypeOneoffForSubscriptionLineItemWhenSubscriptionsDisabled(): void
    {
        $profileId = 'pfl_test_profile';

        $subscriptionSettings = new SubscriptionSettings(enabled: false);
        $builder = $this->createBuilder(profileId: $profileId, subscriptionSettings: $subscriptionSettings);

        $transactionService = new FakeTransactionService();
        $transactionService->withSubscriptionLineItem();
        $transactionData = $transactionService->findById('test', $this->context);

        $actual = $builder->buildPayment($transactionData, new FakeSubscriptionAwarePaymentHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('oneoff', $actual->getSequenceType()->value);
        $this->assertNull($actual->getCustomerId());
    }

    public function testBuildKeepsSequenceTypeOneoffWhenNotRecurringAwareHandler(): void
    {
        $mollieCustomerId = 'cust_test_mollie_id';
        $profileId = 'pfl_test_profile';
        $mandateId = 'tr_test_mandate_id';

        $builder = $this->createBuilder(profileId: $profileId);

        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('mandateId', $mandateId);

        $transactionService = new FakeTransactionService();
        $transactionService->withMollieCustomerId($profileId, $mollieCustomerId);
        $transactionData = $transactionService->findById('test', $this->context);

        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), $requestDataBag, $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('oneoff', $actual->getSequenceType()->value);
        $this->assertNull($actual->getMandateId());
    }

    public function testBuildCreatesCustomerWhenSequenceTypeIsFirstAndNoCustomerId(): void
    {
        $profileId = 'pfl_test_profile';

        $builder = $this->createBuilder(profileId: $profileId);

        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('savePaymentDetails', true);

        $transactionData = (new FakeTransactionService())->findById('test', $this->context);
        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), $requestDataBag, $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('first', $actual->getSequenceType()->value);
        $this->assertNotNull($actual->getCustomerId());
        $this->assertStringStartsWith('cust_fake_', $actual->getCustomerId());
    }

    public function testBuildDoesNotCreateCustomerWhenSequenceTypeIsOneoff(): void
    {
        $profileId = 'pfl_test_profile';

        $builder = $this->createBuilder(profileId: $profileId);

        $transactionData = (new FakeTransactionService())->findById('test', $this->context);
        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('oneoff', $actual->getSequenceType()->value);
        $this->assertNull($actual->getCustomerId());
    }

    public function testBuildDoesNotCreateCustomerWhenCustomerIdAlreadyExists(): void
    {
        $mollieCustomerId = 'cust_existing_mollie_id';
        $profileId = 'pfl_test_profile';

        $builder = $this->createBuilder(profileId: $profileId);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('savePaymentDetails', true);

        $transactionService = new FakeTransactionService();
        $transactionService->withMollieCustomerId($profileId, $mollieCustomerId);

        $transactionData = $transactionService->findById('test', $this->context);

        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), $requestDataBag, $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('first', $actual->getSequenceType()->value);
        $this->assertSame($mollieCustomerId, $actual->getCustomerId());
    }

    public function testBuildDoesNotCreateCustomerWhenGuest(): void
    {
        $profileId = 'pfl_test_profile';

        $builder = $this->createBuilder(profileId: $profileId);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('savePaymentDetails', true);

        $transactionData = (new FakeTransactionService())->findById('test', $this->context);
        $transactionData->getCustomer()->setGuest(true);

        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), $requestDataBag, $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('oneoff', $actual->getSequenceType()->value);
        $this->assertNull($actual->getCustomerId());
    }

    public function testBuildWithEmptyOrderNumberFormat(): void
    {
        $paymentSettings = new PaymentSettings('', 0);
        $builder = $this->createBuilder($paymentSettings);

        $transactionData = (new FakeTransactionService())->findById('test', $this->context);
        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('10000', $actual->getDescription());
    }

    public function testBuildWithEmptyProfileId(): void
    {
        $profileId = 'fake_profile';

        $builder = $this->createBuilder(profileId: '');

        $transactionService = new FakeTransactionService();
        $transactionService->withMollieCustomerId($profileId, 'cust_from_fallback_profile');

        $transactionData = $transactionService->findById('test', $this->context);
        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('cust_from_fallback_profile', $actual->getCustomerId());
    }

    public function testBuildWithNullLineItems(): void
    {
        $builder = $this->createBuilder();

        $transactionService = new FakeTransactionService();
        $transactionService->withNullLineItems();

        $transactionData = $transactionService->findById('test', $this->context);
        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertInstanceOf(LineItemCollection::class, $actual->getLines());
    }

    public function testBuildWithZeroShippingCosts(): void
    {
        $builder = $this->createBuilder();

        $transactionService = new FakeTransactionService();
        $transactionService->withZeroShippingCosts();

        $transactionData = $transactionService->findById('test', $this->context);

        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);

        $lines = $actual->getLines();
        foreach ($lines as $line) {
            $this->assertNotSame('shipping_fee', $line->getType()->value);
        }
    }

    public function testBuildKeepsNegativeShippingDiscountDelivery(): void
    {
        $builder = $this->createBuilder();

        $transactionService = new FakeTransactionService();
        $transactionService->withShippingDiscountDelivery();

        $transactionData = $transactionService->findById('test', $this->context);

        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $discountLine = null;
        foreach ($actual->getLines() as $line) {
            if ($line->getAmount()->getValue() < 0) {
                $discountLine = $line;
            }
        }

        $this->assertNotNull($discountLine, 'negative shipping discount delivery must stay in the payload');
        $this->assertSame('discount', $discountLine->getType()->value);
        $this->assertSame(-4.99, $discountLine->getAmount()->getValue());
        $this->assertSame('Mollie test: free shipping', $discountLine->getDescription());

        foreach ($actual->getLines() as $line) {
            $this->assertNotSame(0.0, $line->getAmount()->getValue(), 'zero-priced delivery promotion placeholder must be filtered out');
        }
    }

    public function testBuildKeepsValidE164PhoneNumber(): void
    {
        $builder = $this->createBuilder();

        $transactionService = new FakeTransactionService();
        $transactionService->withPhoneNumber('+4930123456789');
        $transactionData = $transactionService->findById('test', $this->context);

        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $array = $actual->toArray();
        $this->assertSame('+4930123456789', $array['billingAddress']['phone']);
        $this->assertSame('+4930123456789', $array['shippingAddress']['phone']);
    }

    public function testBuildNormalizesNationalPhoneNumber(): void
    {
        $logger = new FakeLogger();
        $builder = $this->createBuilder(logger: $logger);

        $transactionService = new FakeTransactionService();
        $transactionService->withPhoneNumber('030 / 123 456');
        $transactionData = $transactionService->findById('test', $this->context);

        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $array = $actual->toArray();
        $this->assertSame('+4930123456', $array['billingAddress']['phone']);
        $this->assertSame('+4930123456', $array['shippingAddress']['phone']);

        $this->assertTrue($logger->hasRecordThatContains(LogLevel::INFO, 'normalized to E.164'));
        foreach ($logger->getRecords() as $record) {
            $this->assertStringNotContainsString('030 / 123 456', json_encode($record) ?: '');
        }
    }

    public function testBuildRemovesUnfixablePhoneNumberAndLogsWarning(): void
    {
        $logger = new FakeLogger();
        $builder = $this->createBuilder(logger: $logger);

        $transactionService = new FakeTransactionService();
        $transactionService->withPhoneNumber('call me maybe');
        $transactionData = $transactionService->findById('test', $this->context);

        $actual = $builder->buildPayment($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $array = $actual->toArray();
        $this->assertArrayNotHasKey('phone', $array['billingAddress']);
        $this->assertArrayNotHasKey('phone', $array['shippingAddress']);

        $this->assertTrue($logger->hasRecordThatContains(LogLevel::WARNING, 'E.164'));
        foreach ($logger->getRecords() as $record) {
            $this->assertStringNotContainsString('call me maybe', json_encode($record) ?: '');
        }
    }

    public function testBuildNormalizesPhoneNumberSetByPaymentHandler(): void
    {
        $builder = $this->createBuilder();

        $transactionData = (new FakeTransactionService())->findById('test', $this->context);

        $dataBag = new RequestDataBag(['molliePayPhone' => '0171 / 2345678']);

        $actual = $builder->buildPayment($transactionData, new FakePhoneAwarePaymentMethodHandler(), $dataBag, $this->context);

        $array = $actual->toArray();
        $this->assertSame('+491712345678', $array['billingAddress']['phone']);
    }

    public function testBuildOrderReturnsCreateOrderInstance(): void
    {
        $builder = $this->createBuilder();
        $transactionData = (new FakeTransactionService())->findById('test', $this->context);

        $actual = $builder->buildOrder($transactionData, new FakeOrdersApiAwarePaymentHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreateOrder::class, $actual);
    }

    public function testBuildOrderContainsAuthenticationIdAtRootLevel(): void
    {
        $builder = $this->createBuilder();
        $transactionData = (new FakeTransactionService())->findById('test', $this->context);

        $dataBag = new RequestDataBag();
        $dataBag->set('authenticationId', 'auth_test_123');

        $actual = $builder->buildOrder($transactionData, new FakeOrdersApiAwarePaymentHandler(), $dataBag, $this->context);

        $array = $actual->toArray();

        $this->assertArrayHasKey('authenticationId', $array);
        $this->assertSame('auth_test_123', $array['authenticationId']);
        $this->assertArrayNotHasKey('payment', $array);
    }

    public function testBuildOrderWithoutAuthenticationIdHasNoPaymentSubArray(): void
    {
        $builder = $this->createBuilder();
        $transactionData = (new FakeTransactionService())->findById('test', $this->context);

        $actual = $builder->buildOrder($transactionData, new FakeOrdersApiAwarePaymentHandler(), new RequestDataBag(), $this->context);

        $array = $actual->toArray();

        $this->assertArrayNotHasKey('payment', $array);
    }

    public function testBuildOrderContainsOrderNumberAndRedirectUrl(): void
    {
        $builder = $this->createBuilder();
        $transactionData = (new FakeTransactionService())->findById('test', $this->context);

        $actual = $builder->buildOrder($transactionData, new FakeOrdersApiAwarePaymentHandler(), new RequestDataBag(), $this->context);

        $array = $actual->toArray();

        $this->assertSame('10000', $array['orderNumber']);
        $this->assertArrayHasKey('billingAddress', $array);
        $this->assertArrayHasKey('lines', $array);
        $this->assertArrayHasKey('amount', $array);
    }

    public function testBuildOrderLinesUseNameKey(): void
    {
        $builder = $this->createBuilder();
        $transactionData = (new FakeTransactionService())->findById('test', $this->context);

        $actual = $builder->buildOrder($transactionData, new FakeOrdersApiAwarePaymentHandler(), new RequestDataBag(), $this->context);

        $array = $actual->toArray();
        $lines = $array['lines'];

        $this->assertNotEmpty($lines);
        $this->assertArrayHasKey('name', $lines[0]);
        $this->assertArrayNotHasKey('description', $lines[0]);
    }

    public function testBuildOrderSetsMetadataWithShopwareOrderNumber(): void
    {
        $builder = $this->createBuilder();
        $transactionData = (new FakeTransactionService())->findById('test', $this->context);

        $actual = $builder->buildOrder($transactionData, new FakeOrdersApiAwarePaymentHandler(), new RequestDataBag(), $this->context);

        $array = $actual->toArray();

        $this->assertArrayHasKey('metadata', $array);
        $this->assertSame('10000', $array['metadata']['shopwareOrderNumber']);
    }

    private function createBuilder(?PaymentSettings $paymentSettings = null, ?string $profileId = null, ?SubscriptionSettings $subscriptionSettings = null, ?LoggerInterface $logger = null): PayloadBuilder
    {
        if ($paymentSettings === null) {
            $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        }
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings,profileId: $profileId, subscriptionSettings: $subscriptionSettings);
        $lineItemFilter = new LineItemFilter();
        $roundingDifferenceFixer = new RoundingDifferenceFixer();

        return new PayloadBuilder(new FakeRouteBuilder(), $settingsService, new FakeGateway('test'), new LineItemAnalyzer(), new FakeCustomerRepository(), $lineItemFilter, $roundingDifferenceFixer, $logger ?? new NullLogger());
    }
}
