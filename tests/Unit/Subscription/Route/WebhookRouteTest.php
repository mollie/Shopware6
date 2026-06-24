<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Route;

use Mollie\Shopware\Component\Subscription\Route\WebhookException;
use Mollie\Shopware\Component\Subscription\Route\WebhookRoute;
use Mollie\Shopware\Unit\Fake\FakeOrderTransactionRepository;
use Mollie\Shopware\Unit\Subscription\Fake\FakePaymentWebhookRoute;
use Mollie\Shopware\Unit\Subscription\Fake\FakeRenewRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(WebhookRoute::class)]
final class WebhookRouteTest extends TestCase
{
    public function testNotifyThrowsWhenPaymentIdMissing(): void
    {
        $route = $this->buildRoute(new FakeOrderTransactionRepository(), new FakePaymentWebhookRoute(), new FakeRenewRoute());

        $this->expectException(WebhookException::class);

        $route->notify('subscription-id', new Request(), Context::createDefaultContext());
    }

    public function testNotifyDelegatesToPaymentWebhookWhenTransactionExists(): void
    {
        $repository = new FakeOrderTransactionRepository();
        $repository->setMatchingIds('transaction-id-42');

        $paymentWebhook = new FakePaymentWebhookRoute();
        $renewRoute = new FakeRenewRoute();
        $route = $this->buildRoute($repository, $paymentWebhook, $renewRoute);

        $request = new Request(query: ['id' => 'mollie-payment-id']);
        $route->notify('subscription-id', $request, Context::createDefaultContext());

        $this->assertSame(1, $paymentWebhook->getCallCount());
        $this->assertSame('transaction-id-42', $paymentWebhook->getCalls()[0]['transactionId']);
        $this->assertSame(0, $renewRoute->getCallCount());
    }

    public function testNotifyDelegatesToRenewRouteWhenNoTransactionFound(): void
    {
        $repository = new FakeOrderTransactionRepository();
        // no matching ids configured
        $paymentWebhook = new FakePaymentWebhookRoute();
        $renewRoute = new FakeRenewRoute();
        $route = $this->buildRoute($repository, $paymentWebhook, $renewRoute);

        $request = new Request(query: ['id' => 'mollie-payment-id']);
        $route->notify('subscription-id', $request, Context::createDefaultContext());

        $this->assertSame(0, $paymentWebhook->getCallCount());
        $this->assertSame(1, $renewRoute->getCallCount());
        $this->assertSame('subscription-id', $renewRoute->getCalls()[0]['subscriptionId']);
    }

    public function testNotifyLowercasesSubscriptionIdBeforeDelegating(): void
    {
        $repository = new FakeOrderTransactionRepository();
        $renewRoute = new FakeRenewRoute();
        $route = $this->buildRoute($repository, new FakePaymentWebhookRoute(), $renewRoute);

        $request = new Request(query: ['id' => 'mollie-payment-id']);
        $route->notify('SUBSCRIPTION-ID', $request, Context::createDefaultContext());

        $this->assertSame('subscription-id', $renewRoute->getCalls()[0]['subscriptionId']);
    }

    private function buildRoute(FakeOrderTransactionRepository $repository, FakePaymentWebhookRoute $paymentWebhook, FakeRenewRoute $renewRoute): WebhookRoute
    {
        return new WebhookRoute($repository, $paymentWebhook, $renewRoute, new NullLogger());
    }
}
