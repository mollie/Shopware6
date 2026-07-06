<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\PaymentLink;

use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\LineItemFilter;
use Mollie\Shopware\Component\Payment\LineCollectionBuilder;
use Mollie\Shopware\Component\PaymentLink\PaymentLinkBuilder;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

#[CoversClass(PaymentLinkBuilder::class)]
final class PaymentLinkBuilderTest extends TestCase
{
    public function testBuildProducesPaymentLinkPayload(): void
    {
        $context = new Context(new SystemSource());
        $transactionData = (new FakeTransactionService())->findById('test', $context);

        $gateway = new FakeGateway();
        $gateway->withActivePaymentMethods(['ideal', 'creditcard']);

        $routeBuilder = new FakeRouteBuilder(
            returnUrl: 'https://shop.example/mollie/payment/test',
            webhookUrl: 'https://shop.example/mollie/webhook/test',
        );

        $builder = new PaymentLinkBuilder(
            $routeBuilder,
            $gateway,
            new LineCollectionBuilder(new LineItemFilter()),
            new NullLogger(),
        );

        $createPaymentLink = $builder->build($transactionData);

        $this->assertInstanceOf(CreatePaymentLink::class, $createPaymentLink);

        $array = $createPaymentLink->toArray();
        $this->assertSame(['ideal', 'creditcard'], $array['allowedMethods']);
        $this->assertSame('https://shop.example/mollie/payment/test', $array['redirectUrl']);
        $this->assertSame('https://shop.example/mollie/webhook/test', $array['webhookUrl']);
        $this->assertFalse($array['reusable']);
        $this->assertArrayHasKey('billingAddress', $array);
        $this->assertArrayHasKey('shippingAddress', $array);
        $this->assertArrayHasKey('lines', $array);
        $this->assertNotEmpty($array['lines']);
    }
}
