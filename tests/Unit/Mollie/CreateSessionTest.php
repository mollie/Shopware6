<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreateSession;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\SequenceType;
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

    /**
     * SessionBuilder reads a session back through these accessors to decide whether it still
     * matches the cart. A getter that reports something else than the payload carries would
     * let it reuse a session Mollie holds for a different amount.
     */
    public function testTheScalarAccessorsReportWhatThePayloadCarries(): void
    {
        $createSession = $this->createFullSession();

        $body = $createSession->toArray();

        $this->assertSame($body['description'], $createSession->getDescription());
        $this->assertSame($body['redirectUrl'], $createSession->getRedirectUrl());
        $this->assertSame($body['cancelUrl'], $createSession->getCancelUrl());
        $this->assertSame($body['amount'], $createSession->getAmount()->toArray());
        $this->assertSame($body['sequenceType'], $createSession->getSequenceType()->value);
        $this->assertSame($body['customerId'], $createSession->getCustomerId());
        $this->assertSame($body['profileId'], $createSession->getProfileId());
        $this->assertSame($body['shippingCallbackUrl'], $createSession->getShippingCallbackUrl());
        $this->assertSame($body['requiredCustomerDetails'], $createSession->getRequiredCustomerDetails());
        $this->assertSame($body['metadata'], $createSession->getMetadata());
        $this->assertSame($body['payment']['webhookUrl'], $createSession->getWebhookUrl());
    }

    public function testTheLinesAreReportedAsTheyWereSet(): void
    {
        $this->assertSame('Shirt', $this->createFullSession()->getLines()->first()?->getDescription());
    }

    public function testBillingAndShippingAddressAreKeptApart(): void
    {
        $createSession = $this->createFullSession();

        $this->assertSame('Billing City', $createSession->getBillingAddress()?->getCity());
        $this->assertSame('Shipping City', $createSession->getShippingAddress()?->getCity());
    }

    public function testTheShippingOptionsAreReportedAsTheyWereSet(): void
    {
        $this->assertSame('Express', $this->createFullSession()->getShippingOptions()?->first()?->getDescription());
    }

    private function createSession(): CreateSession
    {
        return new CreateSession('Storefront', 'https://shop.example/finish', new Money(19.99, 'EUR'));
    }

    /**
     * A session with every optional field filled, so nothing is dropped from the payload and
     * every accessor has something to report.
     */
    private function createFullSession(): CreateSession
    {
        $lines = new LineItemCollection();
        $lines->add(new LineItem('Shirt', 2, new Money(9.99, 'EUR'), new Money(19.98, 'EUR')));

        $shippingOptions = new ShippingOptionCollection();
        $shippingOptions->add(new ShippingOption('Express', 'shipping-method-id', new Money(3.99, 'EUR')));

        $createSession = $this->createSession();
        $createSession->setDescription('Express checkout');
        $createSession->setCancelUrl('https://shop.example/cancel');
        $createSession->setLines($lines);
        $createSession->setSequenceType(SequenceType::FIRST);
        $createSession->setBillingAddress(new Address('billing@shop.example', 'Not specified', 'Bill', 'Payer', 'Billing Street 1', '12345', 'Billing City', 'DE'));
        $createSession->setShippingAddress(new Address('shipping@shop.example', 'Not specified', 'Ship', 'Receiver', 'Shipping Street 2', '54321', 'Shipping City', 'NL'));
        $createSession->setCustomerId('cst_customer');
        $createSession->setProfileId('pfl_profile');
        $createSession->setWebhookUrl('https://shop.example/webhook');
        $createSession->setShippingCallbackUrl('https://shop.example/shipping-options');
        $createSession->setShippingOptions($shippingOptions);
        $createSession->setRequiredCustomerDetails(['email']);
        $createSession->setMetadata(['orderId' => 'order-id']);

        return $createSession;
    }
}
