<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Action;

use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Subscription\Action\ConfirmAction;
use Mollie\Shopware\Component\Subscription\Event\ModifyCreateSubscriptionPayloadEvent;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use Mollie\Shopware\Unit\Fake\FakeEventDispatcher;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGateway;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyEntity;

#[CoversClass(ConfirmAction::class)]
final class ConfirmActionTest extends TestCase
{
    private const SUBSCRIPTION_ID = 'subscription-id';
    private const NEW_MOLLIE_ID = 'sub_new123';
    private const MOLLIE_CUSTOMER_ID = 'cst_test123';
    private const MANDATE_ID = 'mdt_test123';
    private const ORDER_NUMBER = '10000';

    public function testConfirmCreatesMollieSubscriptionAndPersistsConfirmedState(): void
    {
        $context = Context::createDefaultContext();
        $repository = new FakeSubscriptionRepository();
        $gateway = new FakeSubscriptionGateway();

        $subscription = SubscriptionEntityBuilder::create()
            ->withId(self::SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::PENDING)
            ->withMetadata(new SubscriptionMetadata('2026-05-01', 1, IntervalUnit::MONTHS))
            ->build();
        $subscription->setDescription('Monthly box');
        $subscription->setAmount(19.99);

        $newMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::NEW_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withNextPaymentDate(new \DateTimeImmutable('+30 days'))
            ->build();
        $gateway->setCreateResponse($newMollieSubscription);

        $action = $this->getAction($repository, $gateway, new FakeEventDispatcher());
        $result = $action->confirm($subscription, $this->buildCurrency('EUR'), self::MANDATE_ID, self::MOLLIE_CUSTOMER_ID, self::ORDER_NUMBER, $context);

        $this->assertSame($newMollieSubscription, $result);
        $this->assertSame(1, $gateway->getCallCount('createSubscription'));
        $this->assertSame(1, $repository->getUpsertCount());

        $payload = $repository->getLastUpsert();
        $this->assertSame(self::SUBSCRIPTION_ID, $payload['id']);
        $this->assertSame(SubscriptionStatus::ACTIVE->value, $payload['status']);
        $this->assertSame(self::NEW_MOLLIE_ID, $payload['mollieId']);
        $this->assertSame(self::MOLLIE_CUSTOMER_ID, $payload['mollieCustomerId']);
        $this->assertSame(self::MANDATE_ID, $payload['mandateId']);
        $this->assertSame($newMollieSubscription->getNextPaymentDate()->format('Y-m-d'), $payload['nextPaymentAt']);
        $this->assertNull($payload['canceledAt']);
        $this->assertSame('confirmed', $payload['historyEntries'][0]['comment']);
        $this->assertSame(SubscriptionStatus::PENDING->value, $payload['historyEntries'][0]['statusFrom']);
        $this->assertSame(SubscriptionStatus::ACTIVE->value, $payload['historyEntries'][0]['statusTo']);
        $this->assertSame(self::NEW_MOLLIE_ID, $payload['historyEntries'][0]['mollieId']);
    }

    public function testConfirmThrowsWhenMollieSubscriptionHasNoNextPaymentDate(): void
    {
        $context = Context::createDefaultContext();
        $repository = new FakeSubscriptionRepository();
        $gateway = new FakeSubscriptionGateway();
        $subscription = SubscriptionEntityBuilder::create()
            ->withId(self::SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::PENDING)
            ->build();

        $newMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::NEW_MOLLIE_ID)
            ->build();
        $gateway->setCreateResponse($newMollieSubscription);

        $action = $this->getAction($repository, $gateway, new FakeEventDispatcher());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/has no next payment date/');

        try {
            $action->confirm($subscription, $this->buildCurrency('EUR'), self::MANDATE_ID, self::MOLLIE_CUSTOMER_ID, self::ORDER_NUMBER, $context);
        } finally {
            $this->assertSame(0, $repository->getUpsertCount());
        }
    }

    public function testConfirmDispatchesModifyCreateSubscriptionPayloadEvent(): void
    {
        $context = Context::createDefaultContext();
        $repository = new FakeSubscriptionRepository();
        $gateway = new FakeSubscriptionGateway();
        $eventDispatcher = new FakeEventDispatcher();

        $subscription = SubscriptionEntityBuilder::create()
            ->withId(self::SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::PENDING)
            ->build();
        $subscription->setDescription('Monthly box');
        $subscription->setAmount(19.99);

        $newMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::NEW_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withNextPaymentDate(new \DateTimeImmutable('+30 days'))
            ->build();
        $gateway->setCreateResponse($newMollieSubscription);

        $action = $this->getAction($repository, $gateway, $eventDispatcher);
        $action->confirm($subscription, $this->buildCurrency('EUR'), self::MANDATE_ID, self::MOLLIE_CUSTOMER_ID, self::ORDER_NUMBER, $context);

        $this->assertInstanceOf(ModifyCreateSubscriptionPayloadEvent::class, $eventDispatcher->getDispatchedEvent());
    }

    public function testConfirmForwardsTimesFromMetadataIntoCreateSubscriptionPayload(): void
    {
        $context = Context::createDefaultContext();
        $repository = new FakeSubscriptionRepository();
        $gateway = new FakeSubscriptionGateway();

        $metadata = new SubscriptionMetadata('2026-05-01', 1, IntervalUnit::MONTHS, 12);
        $subscription = SubscriptionEntityBuilder::create()
            ->withId(self::SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::PENDING)
            ->withMetadata($metadata)
            ->build();
        $subscription->setDescription('Year box');
        $subscription->setAmount(9.99);

        $newMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::NEW_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withNextPaymentDate(new \DateTimeImmutable('+30 days'))
            ->build();
        $gateway->setCreateResponse($newMollieSubscription);

        $action = $this->getAction($repository, $gateway, new FakeEventDispatcher());
        $action->confirm($subscription, $this->buildCurrency('EUR'), self::MANDATE_ID, self::MOLLIE_CUSTOMER_ID, self::ORDER_NUMBER, $context);

        $payloads = $gateway->getCreatePayloads();
        $this->assertCount(1, $payloads);
        $payloadArray = $payloads[0]->toArray();
        $this->assertSame(12, $payloadArray['times']);
    }

    private function getAction(FakeSubscriptionRepository $repository, FakeSubscriptionGateway $gateway, FakeEventDispatcher $eventDispatcher): ConfirmAction
    {
        return new ConfirmAction(
            $repository,
            $gateway,
            new FakeRouteBuilder(),
            $eventDispatcher,
            new NullLogger()
        );
    }

    private function buildCurrency(string $iso): CurrencyEntity
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode($iso);

        return $currency;
    }
}
