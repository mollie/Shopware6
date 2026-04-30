<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Action;

use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Action\CancelAction;
use Mollie\Shopware\Component\Subscription\Action\Exception\SubscriptionNotActiveException;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionCancelledEvent;
use Mollie\Shopware\Component\Subscription\SubscriptionDataService;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGateway;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;

#[CoversClass(CancelAction::class)]
#[CoversClass(SubscriptionNotActiveException::class)]
final class CancelActionTest extends TestCase
{
    private const SUBSCRIPTION_ID = 'subscription-id';
    private const MOLLIE_SUBSCRIPTION_ID = 'sub_test123';
    private const ORDER_NUMBER = '10000';

    public function testExecuteCancelsImmediatelyWhenWithinCancelWindow(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();
        $mollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::MOLLIE_SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withNextPaymentDate(new \DateTimeImmutable('+30 days'))
            ->build();
        $gateway->register($mollieSubscription);

        $action = new CancelAction($repository, $gateway, new NullLogger());

        $result = $action->execute(
            $this->loadSubscriptionData($repository, $context),
            new SubscriptionSettings(enabled: true, cancelDays: 5),
            $mollieSubscription,
            self::ORDER_NUMBER,
            $context
        );

        $this->assertSame($mollieSubscription, $result);
        $this->assertSame(1, $gateway->getCallCount('cancelSubscription'));
        $this->assertSame(1, $repository->getUpsertCount());
        $payload = $repository->getLastUpsert();
        $this->assertSame(SubscriptionStatus::CANCELED->value, $payload['status']);
        $this->assertInstanceOf(\DateTimeInterface::class, $payload['canceledAt']);
        $this->assertNull($payload['nextPaymentAt']);
        $this->assertSame('cancelled', $payload['historyEntries'][0]['comment']);
    }

    public function testExecuteSchedulesDeferredCancelWhenOutsideCancelWindow(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();
        $nextPaymentDate = new \DateTimeImmutable('+1 day');
        $mollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::MOLLIE_SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withNextPaymentDate($nextPaymentDate)
            ->build();
        $gateway->register($mollieSubscription);

        $action = new CancelAction($repository, $gateway, new NullLogger());

        $result = $action->execute(
            $this->loadSubscriptionData($repository, $context),
            new SubscriptionSettings(enabled: true, cancelDays: 5),
            $mollieSubscription,
            self::ORDER_NUMBER,
            $context
        );

        $this->assertSame($mollieSubscription, $result);
        $this->assertSame(0, $gateway->getCallCount('cancelSubscription'));
        $payload = $repository->getLastUpsert();
        $this->assertSame(SubscriptionStatus::CANCELED_AFTER_RENEWAL->value, $payload['status']);
        $this->assertNull($payload['canceledAt']);
        $this->assertSame($nextPaymentDate, $payload['nextPaymentAt']);
        $this->assertStringStartsWith('cancelled after ', $payload['historyEntries'][0]['comment']);
    }

    public function testExecuteThrowsWhenMollieSubscriptionIsNotActive(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();
        $mollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::MOLLIE_SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::COMPLETED)
            ->build();
        $gateway->register($mollieSubscription);

        $action = new CancelAction($repository, $gateway, new NullLogger());

        $this->expectException(SubscriptionNotActiveException::class);

        try {
            $action->execute(
                $this->loadSubscriptionData($repository, $context),
                new SubscriptionSettings(enabled: true, cancelDays: 5),
                $mollieSubscription,
                self::ORDER_NUMBER,
                $context
            );
        } finally {
            $this->assertSame(0, $repository->getUpsertCount());
        }
    }

    public function testExecutePersistsImmediateCancellationWhenShopwareStatusIsPending(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->prepareRepositoryWithSubscription(SubscriptionStatus::PENDING);
        $gateway = new FakeSubscriptionGateway();
        $mollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::MOLLIE_SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->build();
        $gateway->register($mollieSubscription);

        $action = new CancelAction($repository, $gateway, new NullLogger());

        $result = $action->execute(
            $this->loadSubscriptionData($repository, $context),
            new SubscriptionSettings(enabled: true, cancelDays: 5),
            $mollieSubscription,
            self::ORDER_NUMBER,
            $context
        );

        $this->assertSame($mollieSubscription, $result);
        $this->assertSame(0, $gateway->getCallCount('cancelSubscription'));
        $this->assertSame(1, $repository->getUpsertCount());
        $payload = $repository->getLastUpsert();
        $this->assertSame(SubscriptionStatus::CANCELED->value, $payload['status']);
        $this->assertInstanceOf(\DateTimeInterface::class, $payload['canceledAt']);
        $this->assertNull($payload['nextPaymentAt']);
        $this->assertArrayNotHasKey('metadata', $payload);
        $this->assertSame('canceled', $payload['historyEntries'][0]['comment']);
    }

    public function testCancelPendingPersistsImmediateCancellation(): void
    {
        $context = Context::createDefaultContext();
        $subscription = SubscriptionEntityBuilder::create()
            ->withId(self::SUBSCRIPTION_ID)
            ->withMollieId(self::MOLLIE_SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::PENDING)
            ->build();
        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        $action = new CancelAction($repository, new FakeSubscriptionGateway(), new NullLogger());
        $action->cancelPending($subscription, $context);

        $this->assertSame(1, $repository->getUpsertCount());
        $payload = $repository->getLastUpsert();
        $this->assertSame(self::SUBSCRIPTION_ID, $payload['id']);
        $this->assertSame(SubscriptionStatus::CANCELED->value, $payload['status']);
        $this->assertInstanceOf(\DateTimeInterface::class, $payload['canceledAt']);
        $this->assertNull($payload['nextPaymentAt']);
        $this->assertArrayNotHasKey('metadata', $payload);
        $this->assertSame('canceled', $payload['historyEntries'][0]['comment']);
        $this->assertSame(SubscriptionStatus::PENDING->value, $payload['historyEntries'][0]['statusFrom']);
        $this->assertSame(SubscriptionStatus::CANCELED->value, $payload['historyEntries'][0]['statusTo']);
    }

    public function testCancelPendingThrowsLogicExceptionWhenSubscriptionIsNotPending(): void
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId(self::SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->build();
        $repository = new FakeSubscriptionRepository();

        $action = new CancelAction($repository, new FakeSubscriptionGateway(), new NullLogger());

        $this->expectException(\LogicException::class);

        try {
            $action->cancelPending($subscription, Context::createDefaultContext());
        } finally {
            $this->assertSame(0, $repository->getUpsertCount());
        }
    }

    public function testGetEventClassReturnsCancelledEvent(): void
    {
        $action = new CancelAction(new FakeSubscriptionRepository(), new FakeSubscriptionGateway(), new NullLogger());

        $this->assertSame(SubscriptionCancelledEvent::class, $action->getEventClass());
    }

    public function testActionNameIsCancel(): void
    {
        $this->assertSame('cancel', CancelAction::getActioName());
    }

    private function prepareRepositoryWithSubscription(SubscriptionStatus $status = SubscriptionStatus::ACTIVE): FakeSubscriptionRepository
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId(self::SUBSCRIPTION_ID)
            ->withMollieId(self::MOLLIE_SUBSCRIPTION_ID)
            ->withStatus($status)
            ->build();

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        return $repository;
    }

    private function loadSubscriptionData(FakeSubscriptionRepository $repository, Context $context): \Mollie\Shopware\Component\Subscription\SubscriptionDataStruct
    {
        return (new SubscriptionDataService($repository, new NullLogger()))->findById(self::SUBSCRIPTION_ID, $context);
    }
}
