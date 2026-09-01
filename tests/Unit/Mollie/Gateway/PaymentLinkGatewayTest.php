<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\Exception\ApiException;
use Mollie\Shopware\Component\Mollie\Gateway\PaymentLinkGateway;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\PaymentHydrator;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Mollie\SequenceType;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClient;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(PaymentLinkGateway::class)]
final class PaymentLinkGatewayTest extends TestCase
{
    /**
     * PATCH /v2/payment-links/{id} only accepts a subset of the create payload, everything else
     * is rejected with a 422 "Non-existent body parameter".
     */
    public function testUpdateSendsOnlyUpdatableParameters(): void
    {
        $fakeClient = new FakeClient('pl_test', 'open');
        $gateway = new PaymentLinkGateway(new FakeClientFactory($fakeClient), new PaymentHydrator(), new NullLogger());

        $gateway->updatePaymentLink('pl_test', $this->createPaymentLink(), '10000', 'salesChannelId');

        $formParams = $fakeClient->getLastPatchOptions()['form_params'];

        $this->assertSame(['description', 'lines', 'billingAddress', 'shippingAddress', 'allowedMethods'], array_keys($formParams));
        $this->assertSame('Order 10000', $formParams['description']);
        $this->assertSame(['paypal'], $formParams['allowedMethods']);
    }

    public function testCreateSendsTheFullPayload(): void
    {
        $fakeClient = new FakeClient('pl_test', 'open');
        $gateway = new PaymentLinkGateway(new FakeClientFactory($fakeClient), new PaymentHydrator(), new NullLogger());

        $gateway->createPaymentLink($this->createPaymentLink(), '10000', 'salesChannelId');

        $formParams = $fakeClient->getLastPostOptions()['form_params'];

        $this->assertSame(['value' => '10.00', 'currency' => 'EUR'], $formParams['amount']);
        $this->assertSame('https://shop.test/return', $formParams['redirectUrl']);
        $this->assertSame(SequenceType::ONEOFF->value, $formParams['sequenceType']);
        $this->assertSame('https://shop.test/webhook', $formParams['webhookUrl']);
        $this->assertSame('cst_123', $formParams['customerId']);
    }

    public function testTheCreatedLinkCarriesTheIdAndUrlMollieAnswered(): void
    {
        $client = new FakeClient(body: [
            'id' => 'pl_new',
            '_links' => ['paymentLink' => ['href' => 'https://mollie.test/pl_new']],
        ]);

        $paymentLink = $this->gateway($client)->createPaymentLink($this->createPaymentLink(), '10000', 'salesChannelId');

        $this->assertSame('pl_new', $paymentLink->getId());
        $this->assertSame('https://mollie.test/pl_new', $paymentLink->getUrl());
    }

    public function testTheUpdateIsSentToTheLinkOfThatId(): void
    {
        $client = new FakeClient('pl_existing', 'open');

        $this->gateway($client)->updatePaymentLink('pl_existing', $this->createPaymentLink(), '10000', 'salesChannelId');

        $this->assertSame('payment-links/pl_existing', $client->getLastUri());
        $this->assertSame('PATCH', $client->getLastMethod());
    }

    // ------------------------------------------------------- payments of a link

    /**
     * A payment link produces a Mollie payment only once the customer pays, so this is how the
     * order finds out which payment belongs to it.
     */
    public function testThePaymentsOfALinkAreReadFromItsPaymentsEndpoint(): void
    {
        $client = new FakeClient(body: ['_embedded' => ['payments' => []]]);

        $this->gateway($client)->getPaymentLinkPayments('pl_1', '10000', 'salesChannelId');

        $this->assertSame('payment-links/pl_1/payments', $client->getLastUri());
        $this->assertSame('GET', $client->getLastMethod());
    }

    public function testThePaymentsOfALinkAreHydratedAndKeyedByTheirId(): void
    {
        $client = new FakeClient(body: ['_embedded' => ['payments' => [
            ['id' => 'tr_1', 'status' => 'paid'],
            ['id' => 'tr_2', 'status' => 'open'],
        ]]]);

        $payments = $this->gateway($client)->getPaymentLinkPayments('pl_1', '10000', 'salesChannelId');

        $this->assertCount(2, $payments);
        $this->assertSame('tr_1', $payments->get('tr_1')?->getId());
        $this->assertSame(PaymentStatus::OPEN, $payments->get('tr_2')?->getStatus());
    }

    /**
     * A link nobody has paid yet answers without an _embedded block at all.
     */
    public function testALinkWithoutAnyPaymentYieldsAnEmptyCollection(): void
    {
        $client = new FakeClient(body: ['id' => 'pl_1']);

        $payments = $this->gateway($client)->getPaymentLinkPayments('pl_1', '10000', 'salesChannelId');

        $this->assertCount(0, $payments);
    }

    // ------------------------------------------------------------- Mollie errors

    public function testAMollieErrorWhileCreatingALinkBecomesAnApiException(): void
    {
        $this->expectException(ApiException::class);

        $this->gateway(new FakeClient())->createPaymentLink($this->createPaymentLink(), '10000', 'salesChannelId');
    }

    public function testAMollieErrorWhileUpdatingALinkBecomesAnApiException(): void
    {
        $this->expectException(ApiException::class);

        $this->gateway(new FakeClient())->updatePaymentLink('pl_1', $this->createPaymentLink(), '10000', 'salesChannelId');
    }

    public function testAMollieErrorWhileReadingTheLinkPaymentsBecomesAnApiException(): void
    {
        $this->expectException(ApiException::class);

        $this->gateway(new FakeClient())->getPaymentLinkPayments('pl_1', '10000', 'salesChannelId');
    }

    private function gateway(FakeClient $client): PaymentLinkGateway
    {
        return new PaymentLinkGateway(new FakeClientFactory($client), new PaymentHydrator(), new NullLogger());
    }

    private function createPaymentLink(): CreatePaymentLink
    {
        $address = new Address('test@test.de', 'Mr.', 'Test', 'Tester', 'Teststreet 1', '12345', 'Testcity', 'DE');
        $lines = new LineItemCollection([
            new LineItem('Test Product', 1, new Money(10.0, 'EUR'), new Money(10.0, 'EUR')),
        ]);

        $createPaymentLink = new CreatePaymentLink(
            'Order 10000',
            'https://shop.test/return',
            new Money(10.0, 'EUR'),
            $lines,
            $address,
            $address,
            SequenceType::ONEOFF
        );
        $createPaymentLink->setWebhookUrl('https://shop.test/webhook');
        $createPaymentLink->setCustomerId('cst_123');
        $createPaymentLink->setAllowedMethods(['paypal']);

        return $createPaymentLink;
    }
}
