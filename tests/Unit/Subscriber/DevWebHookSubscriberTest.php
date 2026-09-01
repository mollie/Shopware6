<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscriber;

use Mollie\Shopware\Component\Mollie\Gateway\CachedMollieGateway;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Event\PaymentFinalizeEvent;
use Mollie\Shopware\Component\Settings\Struct\EnvironmentSettings;
use Mollie\Shopware\Component\Shipment\CancelItemEvent;
use Mollie\Shopware\Component\Shipment\OrderShippedEvent;
use Mollie\Shopware\Subscriber\DevWebHookSubscriber;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Fake\FakeWebhookRoute;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;

#[CoversClass(DevWebHookSubscriber::class)]
final class DevWebHookSubscriberTest extends TestCase
{
    private FakeWebhookRoute $webhookRoute;

    private FakeLogger $logger;

    protected function setUp(): void
    {
        $this->webhookRoute = new FakeWebhookRoute();
        $this->logger = new FakeLogger();
    }

    public function testSubscribedEvents(): void
    {
        $events = DevWebHookSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(PaymentFinalizeEvent::class, $events);
        self::assertArrayHasKey(OrderShippedEvent::class, $events);
        self::assertArrayHasKey(CancelItemEvent::class, $events);
    }

    #[DataProvider('developmentEnvironments')]
    public function testTheWebhookIsTriggeredLocallyAfterAFinalizedPayment(EnvironmentSettings $environment): void
    {
        // The Mollie webhook cannot reach a local shop, so the finalize step triggers it itself.
        $subscriber = $this->buildSubscriber($environment);

        $subscriber->handleFinalizeEvent($this->finalizeEvent('transaction-id'));

        self::assertSame(['transaction-id'], $this->webhookRoute->getNotifiedTransactionIds());
        self::assertTrue($this->logger->hasRecordThatContains('warning', 'Executing Webhook in Dev mode'));
    }

    /**
     * @return array<string, array{EnvironmentSettings}>
     */
    public static function developmentEnvironments(): array
    {
        return [
            'dev mode' => [new EnvironmentSettings(true, false)],
            'cypress mode' => [new EnvironmentSettings(false, true)],
        ];
    }

    public function testAProductionShopLeavesTheWebhookToMollie(): void
    {
        // In production the status is changed by the redirect and by Mollie's own webhook; triggering
        // it here as well would race with them.
        $subscriber = $this->buildSubscriber(new EnvironmentSettings(false, false));

        $subscriber->handleFinalizeEvent($this->finalizeEvent('transaction-id'));

        self::assertSame([], $this->webhookRoute->getNotifiedTransactionIds());
    }

    public function testAProductionShopDoesNotRetriggerTheWebhookAfterAShipment(): void
    {
        $subscriber = $this->buildSubscriber(new EnvironmentSettings(false, false));

        $subscriber->onOrderShipped(new OrderShippedEvent('transaction-id', Context::createDefaultContext()));

        self::assertSame([], $this->webhookRoute->getNotifiedTransactionIds());
    }

    public function testAProductionShopDoesNotRetriggerTheWebhookAfterACancellation(): void
    {
        $subscriber = $this->buildSubscriber(new EnvironmentSettings(false, false));

        $subscriber->onCancelItem(new CancelItemEvent('transaction-id', Context::createDefaultContext()));

        self::assertSame([], $this->webhookRoute->getNotifiedTransactionIds());
    }

    private function buildSubscriber(EnvironmentSettings $environment): DevWebHookSubscriber
    {
        return new DevWebHookSubscriber(
            new FakeSettingsService(environmentSettings: $environment),
            $this->webhookRoute,
            new CachedMollieGateway(new FakeGateway()),
            $this->logger
        );
    }

    private function finalizeEvent(string $transactionId): PaymentFinalizeEvent
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId($transactionId);

        $payment = new Payment('tr_123');
        $payment->setShopwareTransaction($transaction);

        return new PaymentFinalizeEvent($payment, Context::createDefaultContext());
    }
}
