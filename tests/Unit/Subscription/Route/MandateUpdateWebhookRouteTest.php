<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Route;

use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Subscription\Action\UpdatePaymentMethodAction;
use Mollie\Shopware\Component\Subscription\Route\MandateUpdateWebhookRoute;
use Mollie\Shopware\Component\Subscription\SubscriptionDataStruct;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionDataService;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGateway;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;

#[CoversClass(MandateUpdateWebhookRoute::class)]
final class MandateUpdateWebhookRouteTest extends TestCase
{
    private const SUBSCRIPTION_ID = 'subscription-id';

    private FakeSubscriptionRepository $subscriptionRepository;

    private FakeSubscriptionGateway $subscriptionGateway;

    private FakeLogger $logger;

    protected function setUp(): void
    {
        $this->subscriptionRepository = new FakeSubscriptionRepository();
        $this->subscriptionGateway = new FakeSubscriptionGateway();
        $this->subscriptionGateway->register(MollieSubscriptionBuilder::create()->withId('sub_test123')->build());
        $this->logger = new FakeLogger();
    }

    public function testTheNewMandateIsStoredOnTheSubscription(): void
    {
        $route = $this->buildRoute();

        $response = $route->update(self::SUBSCRIPTION_ID, Context::createDefaultContext());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['success' => true], $this->decode($response));
        self::assertSame('mdt_new', $this->subscriptionRepository->getLastUpsert()['mandateId']);
    }

    public function testTheSubscriptionIdFromTheUrlIsLoweredBeforeTheLookup(): void
    {
        $dataService = new FakeSubscriptionDataService($this->subscriptionData());
        $route = $this->buildRoute($dataService);

        $route->update('SUBSCRIPTION-ID', Context::createDefaultContext());

        self::assertSame([['subscriptionId' => self::SUBSCRIPTION_ID]], $dataService->getCalls());
    }

    public function testAnUnknownSubscriptionIsAnsweredWithAnErrorInsteadOfAnException(): void
    {
        // Mollie retries the webhook, so it must always get a response instead of a stack trace.
        $dataService = new FakeSubscriptionDataService();
        $route = $this->buildRoute($dataService);

        $response = $route->update(self::SUBSCRIPTION_ID, Context::createDefaultContext());

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($this->decode($response)['success']);
    }

    public function testAFailedConfirmationIsLogged(): void
    {
        // Without the temporary transaction there is nothing to confirm.
        $route = $this->buildRoute(new FakeSubscriptionDataService($this->subscriptionData(tmpTransactionId: '')));

        $route->update(self::SUBSCRIPTION_ID, Context::createDefaultContext());

        self::assertTrue($this->logger->hasRecordThatContains('error', 'Subscription mandate update webhook failed'));
    }

    public function testAnUnapprovedPaymentDoesNotChangeTheMandate(): void
    {
        $failedPayment = new Payment('tr_tmp');
        $failedPayment->setStatus(PaymentStatus::FAILED);

        $route = $this->buildRoute(mollieGateway: new FakeGateway(payment: $failedPayment));

        $response = $route->update(self::SUBSCRIPTION_ID, Context::createDefaultContext());

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->subscriptionRepository->getUpsertCount());
    }

    private function buildRoute(
        ?FakeSubscriptionDataService $dataService = null,
        ?FakeGateway $mollieGateway = null,
    ): MandateUpdateWebhookRoute {
        $action = new UpdatePaymentMethodAction(
            $this->subscriptionRepository,
            $this->subscriptionGateway,
            $mollieGateway ?? new FakeGateway(payment: $this->approvedPayment()),
            new FakeRouteBuilder(),
            new PaymentHandlerLocator([]),
            new NullLogger()
        );

        return new MandateUpdateWebhookRoute(
            $dataService ?? new FakeSubscriptionDataService($this->subscriptionData()),
            $action,
            $this->logger
        );
    }

    private function subscriptionData(string $tmpTransactionId = 'tr_tmp'): SubscriptionDataStruct
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId(self::SUBSCRIPTION_ID)
            ->withMollieId('sub_test123')
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withMetadata(new SubscriptionMetadata('2026-06-01', 1, IntervalUnit::MONTHS, 0, $tmpTransactionId))
            ->build()
        ;

        $billingAddress = $subscription->getBillingAddress();
        $shippingAddress = $subscription->getShippingAddress();
        self::assertNotNull($billingAddress);
        self::assertNotNull($shippingAddress);

        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setOrderNumber('10000');

        return new SubscriptionDataStruct(
            $subscription,
            $order,
            CustomerBuilder::create()->build(),
            $billingAddress,
            $shippingAddress
        );
    }

    private function approvedPayment(): Payment
    {
        $payment = new Payment('tr_tmp');
        $payment->setStatus(PaymentStatus::PAID);
        $payment->setMandateId('mdt_new');

        return $payment;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
