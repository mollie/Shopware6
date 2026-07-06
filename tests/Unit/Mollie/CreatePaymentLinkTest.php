<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\LineItemType;
use Mollie\Shopware\Component\Mollie\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreatePaymentLink::class)]
final class CreatePaymentLinkTest extends TestCase
{
    public function testMinimalPayloadContainsRequiredFields(): void
    {
        $createPaymentLink = new CreatePaymentLink('10000', new Money(19.99, 'EUR'));

        $array = $createPaymentLink->toArray();

        $this->assertSame('10000', $array['description']);
        $this->assertSame(['value' => '19.99', 'currency' => 'EUR'], $array['amount']);
        $this->assertFalse($array['reusable']);
        $this->assertArrayNotHasKey('expiresAt', $array);
        $this->assertArrayNotHasKey('redirectUrl', $array);
        $this->assertArrayNotHasKey('webhookUrl', $array);
        $this->assertArrayNotHasKey('lines', $array);
        $this->assertArrayNotHasKey('allowedMethods', $array);
    }

    public function testFullPayloadIsSerialized(): void
    {
        $createPaymentLink = new CreatePaymentLink('10000', new Money(19.99, 'EUR'));
        $createPaymentLink->setRedirectUrl('https://shop.example/mollie/pay/tx/return');
        $createPaymentLink->setWebhookUrl('https://shop.example/mollie/webhook/payment-link/tx');
        $createPaymentLink->setBillingAddress($this->buildAddress());
        $createPaymentLink->setShippingAddress($this->buildAddress());
        $createPaymentLink->setAllowedMethods(['ideal', 'creditcard']);
        $createPaymentLink->setLines($this->buildLines());

        $array = $createPaymentLink->toArray();

        $this->assertSame('https://shop.example/mollie/pay/tx/return', $array['redirectUrl']);
        $this->assertSame('https://shop.example/mollie/webhook/payment-link/tx', $array['webhookUrl']);
        $this->assertSame(['ideal', 'creditcard'], $array['allowedMethods']);
        $this->assertFalse($array['reusable']);

        $this->assertSame('Doe', $array['billingAddress']['familyName']);
        $this->assertSame('Doe', $array['shippingAddress']['familyName']);

        $this->assertCount(1, $array['lines']);
        $this->assertSame('Product', $array['lines'][0]['description']);
        $this->assertSame('physical', $array['lines'][0]['type']);
        $this->assertSame(2, $array['lines'][0]['quantity']);
        $this->assertSame(['value' => '20.00', 'currency' => 'EUR'], $array['lines'][0]['totalAmount']);
    }

    public function testEmptyAllowedMethodsAreOmitted(): void
    {
        $createPaymentLink = new CreatePaymentLink('10000', new Money(19.99, 'EUR'));
        $createPaymentLink->setAllowedMethods([]);

        $this->assertArrayNotHasKey('allowedMethods', $createPaymentLink->toArray());
    }

    private function buildAddress(): Address
    {
        return new Address('john@example.com', 'Mr', 'John', 'Doe', 'Street 1', '12345', 'City', 'DE');
    }

    private function buildLines(): LineItemCollection
    {
        $lineItem = new LineItem('Product', 2, new Money(10.0, 'EUR'), new Money(20.0, 'EUR'));
        $lineItem->setType(LineItemType::PHYSICAL);
        $lineItem->setSku('SKU-1');

        $collection = new LineItemCollection();
        $collection->add($lineItem);

        return $collection;
    }
}
