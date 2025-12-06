<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CaptureMode;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Locale;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\SequenceType;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreatePayment::class)]
final class CreatePaymentTest extends TestCase
{
    public function testConstructor(): void
    {
        $description = 'Test Payment';
        $redirectUrl = 'https://example.com/redirect';
        $amount = new Money(100.50, 'EUR');

        $payment = new CreatePayment($description, $redirectUrl, $amount);

        $this->assertSame($description, $payment->getDescription());
        $this->assertSame($redirectUrl, $payment->getRedirectUrl());
        $this->assertSame($amount, $payment->getAmount());
        $this->assertSame(SequenceType::ONEOFF, $payment->getSequenceType());
    }

    public function testSettersAndGetters(): void
    {
        $billingAddress = new Address(
            'billing@example.com',
            'Mr.',
            'John',
            'Doe',
            'Main Street 1',
            '12345',
            'Berlin',
            'DE'
        );
        $shippingAddress = new Address(
            'shipping@example.com',
            'Mrs.',
            'Jane',
            'Smith',
            'Secondary Street 2',
            '54321',
            'Munich',
            'DE'
        );
        $lineItems = new LineItemCollection();
        $dueDate = new \DateTime('2025-12-31');

        $payment = new CreatePayment('Test', 'https://example.com', new Money(50.00, 'EUR'));
        $payment->setBillingAddress($billingAddress);
        $payment->setShippingAddress($shippingAddress);
        $payment->setMethod(PaymentMethod::PAYPAL);
        $payment->setLocale(Locale::deDE);
        $payment->setSequenceType(SequenceType::RECURRING);
        $payment->setCaptureMode(CaptureMode::MANUAL);
        $payment->setWebhookUrl('https://example.com/webhook');
        $payment->setCardToken('test_card_token_123');
        $payment->setApplePayPaymentToken('test_apple_pay_token_456');
        $payment->setDueDate($dueDate);
        $payment->setDescription('Test');
        $payment->setLines($lineItems);
        $payment->setShopwareOrderNumber('SW-ORDER-123456');

        $this->assertSame('Test', $payment->getDescription());
        $this->assertSame('https://example.com', $payment->getRedirectUrl());
        $this->assertInstanceOf(Money::class, $payment->getAmount());
        $this->assertSame($billingAddress, $payment->getBillingAddress());
        $this->assertSame($shippingAddress, $payment->getShippingAddress());
        $this->assertSame(PaymentMethod::PAYPAL, $payment->getMethod());
        $this->assertSame(Locale::deDE, $payment->getLocale());
        $this->assertSame(SequenceType::RECURRING, $payment->getSequenceType());
        $this->assertSame(CaptureMode::MANUAL, $payment->getCaptureMode());
        $this->assertSame('https://example.com/webhook', $payment->getWebhookUrl());
        $this->assertSame('', $payment->getCancelUrl());
        $this->assertSame('test_card_token_123', $payment->getCardToken());
        $this->assertSame('test_apple_pay_token_456', $payment->getApplePayPaymentToken());
        $this->assertSame($dueDate, $payment->getDueDate());
        $this->assertSame($lineItems, $payment->getLines());
        $this->assertSame('SW-ORDER-123456', $payment->getShopwareOrderNumber());
    }

    public function testApplePayPaymentTokenCanBeSetToNull(): void
    {
        $payment = new CreatePayment('Test', 'https://example.com', new Money(50.00, 'EUR'));

        $payment->setApplePayPaymentToken('token');
        $this->assertSame('token', $payment->getApplePayPaymentToken());

        $payment->setApplePayPaymentToken(null);
        $this->assertNull($payment->getApplePayPaymentToken());
    }

    public function testDueDateCanBeSetToNull(): void
    {
        $payment = new CreatePayment('Test', 'https://example.com', new Money(50.00, 'EUR'));

        $dueDate = new \DateTime('2025-12-31');
        $payment->setDueDate($dueDate);
        $this->assertSame($dueDate, $payment->getDueDate());

        $payment->setDueDate(null);
        $this->assertNull($payment->getDueDate());
    }

    public function testToArray(): void
    {
        $payment = new CreatePayment('Test Payment', 'https://example.com/redirect', new Money(99.99, 'EUR'));
        $payment->setMethod(PaymentMethod::IDEAL);
        $payment->setWebhookUrl('https://example.com/webhook');

        $array = $payment->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('amount', $array);
        $this->assertArrayHasKey('redirectUrl', $array);
        $this->assertArrayHasKey('method', $array);
        $this->assertArrayHasKey('webhookUrl', $array);
        $this->assertSame('Test Payment', $array['description']);
        $this->assertSame('https://example.com/redirect', $array['redirectUrl']);
    }

    public function testToArrayFiltersNullValues(): void
    {
        $payment = new CreatePayment('Test', 'https://example.com', new Money(50.00, 'EUR'));

        $array = $payment->toArray();

        $this->assertArrayNotHasKey('cardToken', $array);
        $this->assertArrayNotHasKey('applePayPaymentToken', $array);
        $this->assertArrayNotHasKey('dueDate', $array);
    }
}
