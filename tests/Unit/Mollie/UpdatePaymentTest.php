<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\Locale;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\UpdatePayment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdatePayment::class)]
final class UpdatePaymentTest extends TestCase
{
    public function testToArrayContainsOnlyUpdatableFields(): void
    {
        $billingAddress = new Address('billing@example.com', 'Mr.', 'John', 'Doe', 'Main Street 1', '12345', 'Berlin', 'DE');
        $shippingAddress = new Address('shipping@example.com', 'Mrs.', 'Jane', 'Smith', 'Second Street 2', '54321', 'Munich', 'DE');

        $payment = new UpdatePayment('Test Payment', 'https://example.com/redirect');
        $payment->setMethod(PaymentMethod::IDEAL);
        $payment->setWebhookUrl('https://example.com/webhook');
        $payment->setCancelUrl('https://example.com/cancel');
        $payment->setLocale(Locale::deDE);
        $payment->setBillingAddress($billingAddress);
        $payment->setShippingAddress($shippingAddress);
        $payment->setShopwareOrderNumber('SW-ORDER-123456');
        $payment->setDueDate(new \DateTime('2025-12-31'));

        $array = $payment->toArray();

        $this->assertSame('Test Payment', $array['description']);
        $this->assertSame('https://example.com/redirect', $array['redirectUrl']);
        $this->assertSame('https://example.com/webhook', $array['webhookUrl']);
        $this->assertSame('https://example.com/cancel', $array['cancelUrl']);
        $this->assertSame(PaymentMethod::IDEAL->value, $array['method']);
        $this->assertSame(Locale::deDE->value, $array['locale']);
        $this->assertArrayHasKey('billingAddress', $array);
        $this->assertArrayHasKey('shippingAddress', $array);
        $this->assertSame('SW-ORDER-123456', $array['metadata']['shopwareOrderNumber']);
        $this->assertSame('2025-12-31', $array['dueDate']);

        $this->assertArrayNotHasKey('amount', $array);
        $this->assertArrayNotHasKey('lines', $array);
        $this->assertArrayNotHasKey('sequenceType', $array);
    }

    public function testToArrayFiltersUnsetValues(): void
    {
        $payment = new UpdatePayment('Test', 'https://example.com/redirect');

        $array = $payment->toArray();

        $this->assertArrayNotHasKey('method', $array);
        $this->assertArrayNotHasKey('cancelUrl', $array);
        $this->assertArrayNotHasKey('webhookUrl', $array);
        $this->assertArrayNotHasKey('locale', $array);
        $this->assertArrayNotHasKey('billingAddress', $array);
        $this->assertArrayNotHasKey('dueDate', $array);
    }
}
