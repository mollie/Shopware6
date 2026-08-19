<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\Gateway\PaymentLinkGateway;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\PaymentHydrator;
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
