<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CaptureMode;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\CreatePaymentBuilder;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Unit\Logger\FakeSettingsService;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeBankTransferAwarePaymentHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeCustomerRepository;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Payment\Fake\FakeManualCaptureModeAwarePaymentHandler;
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
    private Context $context;

    public function setUp(): void
    {
        $this->context = new Context(new SystemSource());
    }

    public function testBuild(): void
    {
        $builder = $this->createBuilder();

        $transactionData = (new FakeTransactionService())->findById('test', $this->context);

        $actual = $builder->build($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);
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
        $actual = $builder->build($transactionData, new FakeManualCaptureModeAwarePaymentHandler(), new RequestDataBag(), $this->context);

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
        $actual = $builder->build($transactionData, new FakeBankTransferAwarePaymentHandler(), new RequestDataBag(), $this->context);

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
        $actual = $builder->build($transactionData, new FakeBankTransferAwarePaymentHandler(), new RequestDataBag(), $this->context);

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

        $actual = $builder->build($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame($mollieCustomerId, $actual->getCustomerId());
    }

    public function testBuildWithoutMollieCustomerId(): void
    {
        $builder = $this->createBuilder();

        $transactionData = (new FakeTransactionService())->findById('test', $this->context);
        $actual = $builder->build($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

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
        $actual = $builder->build($transactionData, new FakePaymentMethodHandler(), $requestDataBag, $this->context);

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

        $actual = $builder->build($transactionData, new FakePaymentMethodHandler(), $requestDataBag, $this->context);

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

        $actual = $builder->build($transactionData, new FakeRecurringAwarePaymentHandler(), $requestDataBag, $this->context);

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
        $actual = $builder->build($transactionData, new FakeRecurringAwarePaymentHandler(), $requestDataBag, $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('first', $actual->getSequenceType()->value);
        $this->assertNull($actual->getMandateId());
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

        $actual = $builder->build($transactionData, new FakePaymentMethodHandler(), $requestDataBag, $this->context);

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
        $actual = $builder->build($transactionData, new FakePaymentMethodHandler(), $requestDataBag, $this->context);

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
        $actual = $builder->build($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

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

        $actual = $builder->build($transactionData, new FakePaymentMethodHandler(), $requestDataBag, $this->context);

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

        $actual = $builder->build($transactionData, new FakePaymentMethodHandler(), $requestDataBag, $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('oneoff', $actual->getSequenceType()->value);
        $this->assertNull($actual->getCustomerId());
    }

    public function testBuildWithEmptyOrderNumberFormat(): void
    {
        $paymentSettings = new PaymentSettings('', 0);
        $builder = $this->createBuilder($paymentSettings);

        $transactionData = (new FakeTransactionService())->findById('test', $this->context);
        $actual = $builder->build($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

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
        $actual = $builder->build($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertSame('cust_from_fallback_profile', $actual->getCustomerId());
    }

    public function testBuildWithNullLineItems(): void
    {
        $builder = $this->createBuilder();

        $transactionService = new FakeTransactionService();
        $transactionService->withNullLineItems();

        $transactionData = $transactionService->findById('test', $this->context);
        $actual = $builder->build($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);
        $this->assertInstanceOf(LineItemCollection::class, $actual->getLines());
    }

    public function testBuildWithZeroShippingCosts(): void
    {
        $builder = $this->createBuilder();

        $transactionService = new FakeTransactionService();
        $transactionService->withZeroShippingCosts();

        $transactionData = $transactionService->findById('test', $this->context);

        $actual = $builder->build($transactionData, new FakePaymentMethodHandler(), new RequestDataBag(), $this->context);

        $this->assertInstanceOf(CreatePayment::class, $actual);

        $lines = $actual->getLines();
        foreach ($lines as $line) {
            $this->assertNotSame('shipping_fee', $line->getType()->value);
        }
    }

    private function createBuilder(?PaymentSettings $paymentSettings = null, ?string $profileId = null): CreatePaymentBuilder
    {
        if ($paymentSettings === null) {
            $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        }
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings,profileId: $profileId);

        return new CreatePaymentBuilder(new FakeRouteBuilder(), $settingsService, new FakeGateway('test'), new FakeCustomerRepository(), new NullLogger());
    }
}
