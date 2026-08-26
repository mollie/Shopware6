<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreateOrder;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\LineItemType;
use Mollie\Shopware\Component\Mollie\Locale;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\SequenceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateOrder::class)]
final class CreateOrderTest extends TestCase
{
    public function testConstructorValuesAreExposed(): void
    {
        $amount = new Money(119.00, 'EUR');
        $lines = new LineItemCollection();
        $billingAddress = $this->createAddress();

        $createOrder = new CreateOrder('SW10001', 'https://shop.example/return', $amount, $lines, $billingAddress, Locale::deDE);

        $this->assertSame('SW10001', $createOrder->getOrderNumber());
        $this->assertSame('https://shop.example/return', $createOrder->getRedirectUrl());
        $this->assertSame($amount, $createOrder->getAmount());
        $this->assertSame($lines, $createOrder->getLines());
        $this->assertSame($billingAddress, $createOrder->getBillingAddress());
        $this->assertSame(Locale::deDE, $createOrder->getLocale());
    }

    public function testMinimalPayloadHoldsOnlyTheRequiredFields(): void
    {
        $createOrder = $this->createOrder();

        $payload = $createOrder->toArray();

        $this->assertEqualsCanonicalizing(['amount', 'orderNumber', 'lines', 'billingAddress', 'locale', 'redirectUrl'], array_keys($payload));
        $this->assertSame(['value' => '119.00', 'currency' => 'EUR'], $payload['amount']);
        $this->assertSame('SW10001', $payload['orderNumber']);
        $this->assertSame('de_DE', $payload['locale']);
        $this->assertSame('https://shop.example/return', $payload['redirectUrl']);
    }

    public function testBillingAddressIsSerializedIntoThePayload(): void
    {
        $createOrder = $this->createOrder();

        $payload = $createOrder->toArray();

        $this->assertSame('Doe', $payload['billingAddress']['familyName']);
        $this->assertSame('DE', $payload['billingAddress']['country']);
    }

    public function testShippingAddressIsOmittedWhenItWasNeverSet(): void
    {
        $createOrder = $this->createOrder();

        $payload = $createOrder->toArray();

        $this->assertArrayNotHasKey('shippingAddress', $payload);
    }

    public function testShippingAddressIsSerializedWhenItIsSet(): void
    {
        $shippingAddress = new Address('jane@example.com', 'Mrs.', 'Jane', 'Roe', 'Second Street 2', '54321', 'Munich', 'AT');
        $createOrder = $this->createOrder();
        $createOrder->setShippingAddress($shippingAddress);

        $payload = $createOrder->toArray();

        $this->assertSame($shippingAddress, $createOrder->getShippingAddress());
        $this->assertSame('Roe', $payload['shippingAddress']['familyName']);
        $this->assertSame('AT', $payload['shippingAddress']['country']);
    }

    public function testWebhookUrlIsOmittedWhileItIsEmpty(): void
    {
        $createOrder = $this->createOrder();

        $this->assertSame('', $createOrder->getWebhookUrl());
        $this->assertArrayNotHasKey('webhookUrl', $createOrder->toArray());
    }

    public function testWebhookUrlIsAddedWhenItIsSet(): void
    {
        $createOrder = $this->createOrder();
        $createOrder->setWebhookUrl('https://shop.example/webhook');

        $this->assertSame('https://shop.example/webhook', $createOrder->getWebhookUrl());
        $this->assertSame('https://shop.example/webhook', $createOrder->toArray()['webhookUrl']);
    }

    public function testMethodIsOmittedWhenNoneWasChosen(): void
    {
        $createOrder = $this->createOrder();

        $this->assertNull($createOrder->getMethod());
        $this->assertArrayNotHasKey('method', $createOrder->toArray());
    }

    public function testMethodIsWrittenAsItsMollieIdentifier(): void
    {
        $createOrder = $this->createOrder();
        $createOrder->setMethod(PaymentMethod::PAYPAL);

        $this->assertSame(PaymentMethod::PAYPAL, $createOrder->getMethod());
        $this->assertSame('paypal', $createOrder->toArray()['method']);
    }

    public function testEmptyMetadataIsNotTransmitted(): void
    {
        $createOrder = $this->createOrder();
        $createOrder->setMetadata([]);

        $this->assertArrayNotHasKey('metadata', $createOrder->toArray());
    }

    public function testMetadataIsTransmittedWhenItHasContent(): void
    {
        $createOrder = $this->createOrder();
        $createOrder->setMetadata(['orderId' => 'abc']);

        $this->assertSame(['orderId' => 'abc'], $createOrder->toArray()['metadata']);
    }

    public function testAuthenticationIdSitsAtTheRootAndNotInsideThePaymentSubArray(): void
    {
        $createOrder = $this->createOrder();
        $createOrder->setAuthenticationId('auth_123');

        $payload = $createOrder->toArray();

        $this->assertSame('auth_123', $payload['authenticationId']);
        $this->assertArrayNotHasKey('payment', $payload);
    }

    public function testPaymentSpecificParametersAreCollectedInThePaymentSubArray(): void
    {
        $createOrder = $this->createOrder();
        $createOrder->setCardToken('tkn_card');
        $createOrder->setApplePayPaymentToken('tkn_apple');
        $createOrder->setCustomerReference('CUST-1');
        $createOrder->setCustomerId('cst_123');
        $createOrder->setSequenceType(SequenceType::FIRST);
        $createOrder->storeCredentials();

        $payment = $createOrder->toArray()['payment'];

        $this->assertSame([
            'cardToken' => 'tkn_card',
            'applePayPaymentToken' => 'tkn_apple',
            'customerReference' => 'CUST-1',
            'customerId' => 'cst_123',
            'sequenceType' => 'first',
            'storeCredentials' => true,
        ], $payment);
    }

    public function testTerminalIdIsIgnoredBecausePointOfSaleHasNoOrdersApi(): void
    {
        $createOrder = $this->createOrder();
        $createOrder->setTerminalId('term_123');

        $this->assertArrayNotHasKey('payment', $createOrder->toArray());
    }

    public function testMandateIdIsNeverSetForAnOrder(): void
    {
        $this->assertNull($this->createOrder()->getMandateId());
    }

    public function testLineIsTranslatedIntoTheOrdersApiShape(): void
    {
        $line = new LineItem('Product A', 2, new Money(10.00, 'EUR'), new Money(20.00, 'EUR'));
        $line->setType(LineItemType::PHYSICAL);
        $line->setVatRate('19.00');
        $line->setVatAmount(new Money(3.19, 'EUR'));
        $line->setSku('SKU-A');
        $line->setMetadata(['orderLineItemId' => 'line-1']);

        $payload = $this->createOrder(new LineItemCollection([$line]))->toArray();

        $this->assertSame([
            'type' => 'physical',
            'name' => 'Product A',
            'quantity' => 2,
            'unitPrice' => ['value' => '10.00', 'currency' => 'EUR'],
            'totalAmount' => ['value' => '20.00', 'currency' => 'EUR'],
            'vatRate' => '19.00',
            'vatAmount' => ['value' => '3.19', 'currency' => 'EUR'],
            'sku' => 'SKU-A',
            'metadata' => ['orderLineItemId' => 'line-1'],
        ], $payload['lines'][0]);
    }

    public function testLineWithoutTaxOmitsVatRateAndVatAmount(): void
    {
        $line = new LineItem('Tax free', 1, new Money(10.00, 'EUR'), new Money(10.00, 'EUR'));
        $line->setSku('SKU-FREE');

        $payload = $this->createOrder(new LineItemCollection([$line]))->toArray();

        $this->assertArrayNotHasKey('vatRate', $payload['lines'][0]);
        $this->assertArrayNotHasKey('vatAmount', $payload['lines'][0]);
    }

    public function testLineWithoutSkuOmitsTheSkuField(): void
    {
        $line = new LineItem('No sku', 1, new Money(10.00, 'EUR'), new Money(10.00, 'EUR'));
        $line->setSku('');

        $payload = $this->createOrder(new LineItemCollection([$line]))->toArray();

        $this->assertArrayNotHasKey('sku', $payload['lines'][0]);
    }

    public function testLineWithoutMetadataOmitsTheMetadataField(): void
    {
        $line = new LineItem('No metadata', 1, new Money(10.00, 'EUR'), new Money(10.00, 'EUR'));
        $line->setSku('SKU-NO-META');

        $payload = $this->createOrder(new LineItemCollection([$line]))->toArray();

        $this->assertArrayNotHasKey('metadata', $payload['lines'][0]);
    }

    private function createOrder(?LineItemCollection $lines = null): CreateOrder
    {
        return new CreateOrder(
            'SW10001',
            'https://shop.example/return',
            new Money(119.00, 'EUR'),
            $lines ?? new LineItemCollection(),
            $this->createAddress(),
            Locale::deDE
        );
    }

    private function createAddress(): Address
    {
        return new Address('john@example.com', 'Mr.', 'John', 'Doe', 'Main Street 1', '12345', 'Berlin', 'DE');
    }
}
