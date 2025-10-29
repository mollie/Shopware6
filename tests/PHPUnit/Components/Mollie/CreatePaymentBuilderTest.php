<?php
declare(strict_types=1);

namespace Mollie\PHPUnit\Components\Mollie;

use Mollie\Shopware\Component\Mollie\CreatePaymentBuilder;
use Mollie\Shopware\Component\Transaction\PaymentTransactionStruct;
use MolliePayments\Tests\Fakes\FakeRouteBuilder;
use MolliePayments\Tests\Traits\OrderTrait;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

class CreatePaymentBuilderTest extends TestCase
{
    use OrderTrait;

    public function testSimpleBuild(): void
    {
        $routeBuilder = new FakeRouteBuilder('https://shop.localhost/return', 'https://shop.localhost/webhook');
        $builder = new CreatePaymentBuilder($routeBuilder);
        $order = new OrderEntity();

        $country = new CountryEntity();
        $country->setIso('DE');
        $country->setName('Deutschland');
        $salutation = new SalutationEntity();
        $salutation->setDisplayName('Mr.');

        $order->setOrderNumber('10000');
        $order->setAmountNet(100.0000);
        $order->setAmountTotal(100.0000);
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
        $language = new LanguageEntity();

        $locale = new LocaleEntity();
        $locale->setCode('de-DE');
        $language->setLocale($locale);
        $order->setLanguage($language);
        $customer = new OrderCustomerEntity();
        $customer->setEmail('test@test.de');
        $customer->setFirstName('Max');
        $customer->setLastName('Mollie');
        $customer->setCompany('Mollie');
        $customer->setSalutation($salutation);
        $order->setOrderCustomer($customer);

        $orderAddress = new OrderAddressEntity();

        $orderAddress->setFirstName($customer->getFirstName());
        $orderAddress->setLastName($customer->getLastName());
        $orderAddress->setCompany($customer->getCompany());
        $orderAddress->setStreet('Test Street 12');
        $orderAddress->setZipcode('12345');
        $orderAddress->setCity('Test City');
        $orderAddress->setCountry($country);

        $order->setBillingAddress($orderAddress);

        $orderDeliveries = new OrderDeliveryCollection();

        $oderDelivery = $this->getOrderDelivery('test', 0.87, 19, 4.99);
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setName('DHL');
        $shippingMethod->setId('test-shipping');
        $oderDelivery->setShippingOrderAddress($orderAddress);
        $oderDelivery->setShippingMethod($shippingMethod);
        $orderDeliveries->add($oderDelivery);

        $order->setDeliveries($orderDeliveries);

        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');
        $order->setCurrency($currency);

        $paymentTransaction = new PaymentTransactionStruct('test', 'test', $order, new OrderTransactionEntity());

        $actual = $builder->build($paymentTransaction);

        $expected = [
            'description' => '10000',
            'amount' => [
                'currency' => 'EUR',
                'value' => '100.00',
            ],
            'redirectUrl' => 'https://shop.localhost/return',
            'cancelUrl' => '',
            'webhookUrl' => 'https://shop.localhost/webhook',
            'method' => '',
            'billingAddress' => [
                'title' => 'Mr.',
                'givenName' => 'Max',
                'familyName' => 'Mollie',
                'streetAndNumber' => 'Test Street 12',
                'postalCode' => '12345',
                'email' => 'test@test.de',
                'city' => 'Test City',
                'country' => 'DE',
            ],
            'shippingAddress' => [
                'title' => 'Mr.',
                'givenName' => 'Max',
                'familyName' => 'Mollie',
                'streetAndNumber' => 'Test Street 12',
                'postalCode' => '12345',
                'email' => 'test@test.de',
                'city' => 'Test City',
                'country' => 'DE',
            ],
            'captureMode' => null,
            'locale' => 'en_GB',
            'lines' => [
                [
                    'type' => 'shipping_fee',
                    'vatRate' => '19',
                    'vatAmount' => [
                        'currency' => 'EUR',
                        'value' => '0.87',
                    ],
                    'sku' => 'mol-delivery-test-shipping',
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
        ];

        Assert::assertEquals($expected, $actual->toArray());
    }
}
