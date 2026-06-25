<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\FlowBuilder;

use Mollie\Shopware\Integration\Data\ShopwareTestBehaviour;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * Guards against forgotten flow event registrations: every Mollie event that
 * is dispatched at runtime must also be selectable as a flow trigger in the
 * admin. A dispatched-but-unregistered event silently works only for manually
 * named flows and is invisible in Flow Builder.
 */
final class BusinessEventRegistrationTest extends TestCase
{
    use ShopwareTestBehaviour;
    use IntegrationTestBehaviour;

    #[DataProvider('expectedEventNameProvider')]
    public function testEventIsRegisteredAsFlowTrigger(string $eventName): void
    {
        $collection = $this->collectBusinessEvents();

        $this->assertNotNull(
            $collection->get($eventName),
            sprintf('Event "%s" is dispatched but not registered as a flow trigger', $eventName)
        );
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function expectedEventNameProvider(): array
    {
        return [
            // payment / webhook / refund
            'payment success' => ['mollie.payment.success'],
            'payment failed' => ['mollie.payment.failed'],
            'payment cancelled' => ['mollie.payment.cancelled'],
            'refund started' => ['mollie.refund.started'],
            'webhook all' => ['mollie.webhook_received.All'],
            'webhook open' => ['mollie.webhook_received.status.open'],
            'webhook pending' => ['mollie.webhook_received.status.pending'],
            'webhook authorized' => ['mollie.webhook_received.status.authorized'],
            'webhook paid' => ['mollie.webhook_received.status.paid'],
            'webhook canceled' => ['mollie.webhook_received.status.canceled'],
            'webhook expired' => ['mollie.webhook_received.status.expired'],
            'webhook failed' => ['mollie.webhook_received.status.failed'],
            // subscription lifecycle
            'subscription started' => ['mollie.subscription.started'],
            'subscription ended' => ['mollie.subscription.ended'],
            'subscription renewed' => ['mollie.subscription.renewed'],
            'subscription cancelled' => ['mollie.subscription.cancelled'],
            'subscription paused' => ['mollie.subscription.paused'],
            'subscription resumed' => ['mollie.subscription.resumed'],
            'subscription skipped' => ['mollie.subscription.skipped'],
            'subscription renewal_reminder' => ['mollie.subscription.renewal_reminder'],
            'subscription priceChangeNotice' => ['mollie.subscription.priceChangeNotice'],
        ];
    }

    private function collectBusinessEvents(): \Shopware\Core\Framework\Event\BusinessEventCollectorResponse
    {
        /** @var BusinessEventCollector $collector */
        $collector = $this->getContainer()->get(BusinessEventCollector::class);

        return $collector->collect(Context::createDefaultContext());
    }
}
