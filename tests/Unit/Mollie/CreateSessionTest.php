<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\CreateSession;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\ShippingOption;
use Mollie\Shopware\Component\Mollie\ShippingOptionCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateSession::class)]
final class CreateSessionTest extends TestCase
{
    public function testMinimalPayload(): void
    {
        $body = $this->createSession()->toArray();

        $this->assertSame('Storefront', $body['description']);
        $this->assertSame('https://shop.example/finish', $body['redirectUrl']);
        $this->assertSame(['value' => '19.99', 'currency' => 'EUR'], $body['amount']);
        $this->assertSame('oneoff', $body['sequenceType']);
    }

    /**
     * Empty and null values are dropped so Mollie does not reject the payload for fields we
     * simply have nothing to say about.
     */
    public function testUnsetValuesAreNotSent(): void
    {
        $body = $this->createSession()->toArray();

        $this->assertArrayNotHasKey('billingAddress', $body);
        $this->assertArrayNotHasKey('shippingAddress', $body);
        $this->assertArrayNotHasKey('customerId', $body);
        $this->assertArrayNotHasKey('requiredCustomerDetails', $body);
        $this->assertArrayNotHasKey('shippingOptions', $body);
        $this->assertArrayNotHasKey('shippingCallbackUrl', $body);
        $this->assertArrayNotHasKey('payment', $body);
    }

    /**
     * A session carries its webhook inside the payment sub object, not at root level.
     */
    public function testWebhookUrlMovesIntoThePaymentObject(): void
    {
        $createSession = $this->createSession();
        $createSession->setWebhookUrl('https://shop.example/webhook');

        $body = $createSession->toArray();

        $this->assertArrayNotHasKey('webhookUrl', $body);
        $this->assertSame(['webhookUrl' => 'https://shop.example/webhook'], $body['payment']);
    }

    public function testLinesAreSerializedAsAList(): void
    {
        $lines = new LineItemCollection();
        $lines->add(new LineItem('Shirt', 2, new Money(9.99, 'EUR'), new Money(19.98, 'EUR')));

        $createSession = $this->createSession();
        $createSession->setLines($lines);

        $body = $createSession->toArray();

        $this->assertCount(1, $body['lines']);
        $this->assertSame('Shirt', $body['lines'][0]['description']);
        $this->assertSame(2, $body['lines'][0]['quantity']);
        $this->assertSame(['value' => '19.98', 'currency' => 'EUR'], $body['lines'][0]['totalAmount']);
    }

    public function testShippingOptionsAndCallbackUrl(): void
    {
        $shippingOptions = new ShippingOptionCollection();
        $shippingOptions->add(new ShippingOption('Express', 'shipping-method-id', new Money(3.99, 'EUR')));

        $createSession = $this->createSession();
        $createSession->setShippingOptions($shippingOptions);
        $createSession->setShippingCallbackUrl('https://shop.example/shipping-options');

        $body = $createSession->toArray();

        $this->assertSame('https://shop.example/shipping-options', $body['shippingCallbackUrl']);
        $this->assertCount(1, $body['shippingOptions']);
        $this->assertSame('Express', $body['shippingOptions'][0]['description']);
        $this->assertSame('shipping-method-id', $body['shippingOptions'][0]['reference']);
        $this->assertSame(['value' => '3.99', 'currency' => 'EUR'], $body['shippingOptions'][0]['amount']);
    }

    public function testRequiredCustomerDetails(): void
    {
        $createSession = $this->createSession();
        $createSession->setRequiredCustomerDetails(['email', 'billing-address', 'shipping-address']);

        $body = $createSession->toArray();

        $this->assertSame(['email', 'billing-address', 'shipping-address'], $body['requiredCustomerDetails']);
    }

    private function createSession(): CreateSession
    {
        return new CreateSession('Storefront', 'https://shop.example/finish', new Money(19.99, 'EUR'));
    }
}
