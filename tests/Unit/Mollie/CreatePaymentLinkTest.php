<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\SequenceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreatePaymentLink::class)]
final class CreatePaymentLinkTest extends TestCase
{
    public function testPayloadCarriesTheLinkFields(): void
    {
        $payload = $this->createPaymentLink()->toArray();

        $this->assertSame('Order 10000', $payload['description']);
        $this->assertSame(['value' => '119.00', 'currency' => 'EUR'], $payload['amount']);
        $this->assertSame('https://shop.example/return', $payload['redirectUrl']);
        $this->assertSame('oneoff', $payload['sequenceType']);
        $this->assertSame('Doe', $payload['billingAddress']['familyName']);
        $this->assertSame('Roe', $payload['shippingAddress']['familyName']);
    }

    public function testALinkIsNotReusable(): void
    {
        $this->assertFalse($this->createPaymentLink()->toArray()['reusable']);
    }

    public function testLinesAreSerializedIntoThePayload(): void
    {
        $line = new LineItem('Product A', 2, new Money(10.00, 'EUR'), new Money(20.00, 'EUR'));
        $line->setSku('SW10001');

        $payload = $this->createPaymentLink(new LineItemCollection([$line]))->toArray();

        $this->assertSame('Product A', $payload['lines'][0]['description']);
        $this->assertSame(['value' => '20.00', 'currency' => 'EUR'], $payload['lines'][0]['totalAmount']);
    }

    public function testOptionalFieldsAreOmittedWhileTheyAreUnset(): void
    {
        $payload = $this->createPaymentLink()->toArray();

        $this->assertArrayNotHasKey('webhookUrl', $payload);
        $this->assertArrayNotHasKey('allowedMethods', $payload);
        $this->assertArrayNotHasKey('customerId', $payload);
    }

    public function testOptionalFieldsAreAddedOnceTheyAreSet(): void
    {
        $createPaymentLink = $this->createPaymentLink();
        $createPaymentLink->setWebhookUrl('https://shop.example/webhook');
        $createPaymentLink->setAllowedMethods(['ideal', 'paypal']);
        $createPaymentLink->setCustomerId('cst_1');

        $payload = $createPaymentLink->toArray();

        $this->assertSame('https://shop.example/webhook', $payload['webhookUrl']);
        $this->assertSame(['ideal', 'paypal'], $payload['allowedMethods']);
        $this->assertSame('cst_1', $payload['customerId']);
    }

    public function testAllowedMethodsAreReindexedSoMollieReceivesAList(): void
    {
        $createPaymentLink = $this->createPaymentLink();
        $createPaymentLink->setAllowedMethods([3 => 'ideal', 7 => 'paypal']);

        $this->assertSame(['ideal', 'paypal'], $createPaymentLink->toArray()['allowedMethods']);
    }

    public function testUpdatePayloadOnlyHoldsTheFieldsMollieAcceptsForAnUpdate(): void
    {
        $createPaymentLink = $this->createPaymentLink();
        $createPaymentLink->setAllowedMethods(['ideal']);
        $createPaymentLink->setWebhookUrl('https://shop.example/webhook');

        $payload = $createPaymentLink->toUpdateArray();

        $this->assertSame(['description', 'lines', 'billingAddress', 'shippingAddress', 'allowedMethods'], array_keys($payload));
        $this->assertArrayNotHasKey('amount', $payload);
        $this->assertArrayNotHasKey('redirectUrl', $payload);
        $this->assertArrayNotHasKey('webhookUrl', $payload);
    }

    private function createPaymentLink(?LineItemCollection $lines = null): CreatePaymentLink
    {
        return new CreatePaymentLink(
            'Order 10000',
            'https://shop.example/return',
            new Money(119.00, 'EUR'),
            $lines ?? new LineItemCollection(),
            new Address('john@example.com', 'Mr.', 'John', 'Doe', 'Main Street 1', '12345', 'Berlin', 'DE'),
            new Address('jane@example.com', 'Mrs.', 'Jane', 'Roe', 'Second Street 2', '54321', 'Munich', 'AT'),
            SequenceType::ONEOFF
        );
    }
}
