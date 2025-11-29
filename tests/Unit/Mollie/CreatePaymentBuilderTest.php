<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\CreatePaymentBuilder;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Unit\Logger\FakeSettingsService;
use Mollie\Shopware\Unit\Mollie\Fake\FakeOrderRepository;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreatePaymentBuilder::class)]
final class CreatePaymentBuilderTest extends TestCase
{
    public function testBuild(): void
    {
        $orderRepository = new FakeOrderRepository();

        $fakeRouteBuilder = new FakeRouteBuilder();
        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}');
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings);

        $builder = new CreatePaymentBuilder($fakeRouteBuilder, $settingsService);

        $actual = $builder->build('test', $orderRepository->getDefaultOrder());
        $actual->setCardToken('testCard');
        $actual->setMethod('paypal');

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
            'captureMode' => 'automatic',
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
        ];
        $this->assertSame($expected, $actual->toArray());

        $this->assertSame($expected['cardToken'], $actual->getCardToken());
        $this->assertSame($expected['description'], $actual->getDescription());
        $this->assertInstanceOf(Address::class, $actual->getShippingAddress());
        $this->assertInstanceOf(Address::class, $actual->getBillingAddress());
        $this->assertInstanceOf(LineItemCollection::class, $actual->getLines());
        $this->assertEquals(new Money(100.00, 'EUR'), $actual->getAmount());
        $this->assertSame($expected['method'], $actual->getMethod());
        $this->assertEquals($expected['locale'], (string) $actual->getLocale());
        $this->assertSame($expected['webhookUrl'], $actual->getWebhookUrl());
        $this->assertSame($expected['redirectUrl'], $actual->getRedirectUrl());
        $this->assertSame($expected['sequenceType'], (string) $actual->getSequenceType());
        $this->assertSame($expected['captureMode'], (string) $actual->getCaptureMode());
    }

    public function testSetters(): void
    {
        $createPayment = new CreatePayment('test', '', new Money(10.00, 'EUR'));
        $createPayment->setDescription('test2');

        $this->assertSame(null, $createPayment->getCaptureMode());
        $this->assertSame('test2', $createPayment->getDescription());
    }
}
