<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Action;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Subscription\Action\CancelAction;
use Mollie\Shopware\Component\Subscription\Action\PauseAction;
use Mollie\Shopware\Component\Subscription\Action\RenewAction;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionEndedEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionRenewedEvent;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionActionHandler;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;

#[CoversClass(RenewAction::class)]
final class RenewActionTest extends TestCase
{
    public function testExecutePersistsHistoryAndDispatchesRenewedEvent(): void
    {
        $repository = new FakeSubscriptionRepository();
        $eventDispatcher = new EventSpy();
        $action = new RenewAction($repository, $eventDispatcher, new FakeSubscriptionActionHandler(), new NullLogger());

        $subscription = SubscriptionEntityBuilder::create()->withId('subscription-id')->build();
        $mollieSubscription = MollieSubscriptionBuilder::create()
            ->withId('sub_test123')
            ->withNextPaymentDate(new \DateTimeImmutable('2099-06-01'))
            ->build()
        ;
        $payment = new Payment('payment-id');
        $payment->setStatus(PaymentStatus::PAID);

        $action->execute(
            $subscription,
            $mollieSubscription,
            $payment,
            SubscriptionStatus::ACTIVE,
            CustomerBuilder::create()->build(),
            null,
            Context::createDefaultContext()
        );

        $this->assertSame(1, $repository->getUpsertCount());
        $payload = $repository->getLastUpsert();
        $this->assertSame('subscription-id', $payload['id']);
        $this->assertSame('2099-06-01', $payload['nextPaymentAt']);
        $this->assertCount(1, $payload['historyEntries']);
        $this->assertSame('renewed', $payload['historyEntries'][0]['comment']);
        $this->assertSame(SubscriptionStatus::ACTIVE->value, $payload['historyEntries'][0]['statusFrom']);
        $this->assertSame(SubscriptionStatus::ACTIVE->value, $payload['historyEntries'][0]['statusTo']);

        $events = $eventDispatcher->getEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(SubscriptionRenewedEvent::class, $events[0]);
    }

    public function testExecuteAddsResumedHistoryWhenPreviousStatusIsInterrupted(): void
    {
        $repository = new FakeSubscriptionRepository();
        $action = new RenewAction($repository, new EventSpy(), new FakeSubscriptionActionHandler(), new NullLogger());

        $action->execute(
            SubscriptionEntityBuilder::create()->build(),
            MollieSubscriptionBuilder::create()->withNextPaymentDate(new \DateTimeImmutable('2099-06-01'))->build(),
            new Payment('payment-id'),
            SubscriptionStatus::PAUSED,
            CustomerBuilder::create()->build(),
            null,
            Context::createDefaultContext()
        );

        $payload = $repository->getLastUpsert();
        $this->assertCount(2, $payload['historyEntries']);
        $this->assertSame('resumed', $payload['historyEntries'][0]['comment']);
        $this->assertSame(SubscriptionStatus::PAUSED->value, $payload['historyEntries'][0]['statusFrom']);
        $this->assertSame(SubscriptionStatus::RESUMED->value, $payload['historyEntries'][0]['statusTo']);
        $this->assertSame('renewed', $payload['historyEntries'][1]['comment']);
        $this->assertSame(SubscriptionStatus::RESUMED->value, $payload['historyEntries'][1]['statusFrom']);
    }

    public function testExecuteUsesTodayWhenMollieNextPaymentDateIsInPast(): void
    {
        $repository = new FakeSubscriptionRepository();
        $action = new RenewAction($repository, new EventSpy(), new FakeSubscriptionActionHandler(), new NullLogger());

        $action->execute(
            SubscriptionEntityBuilder::create()->build(),
            MollieSubscriptionBuilder::create()->withNextPaymentDate(new \DateTimeImmutable('1999-01-01'))->build(),
            new Payment('payment-id'),
            SubscriptionStatus::ACTIVE,
            CustomerBuilder::create()->build(),
            null,
            Context::createDefaultContext()
        );

        $payload = $repository->getLastUpsert();
        $this->assertSame((new \DateTime())->format('Y-m-d'), $payload['nextPaymentAt']);
    }

    public function testExecuteDispatchesEndedEventWhenMollieSubscriptionIsCompleted(): void
    {
        $eventDispatcher = new EventSpy();
        $action = new RenewAction(new FakeSubscriptionRepository(), $eventDispatcher, new FakeSubscriptionActionHandler(), new NullLogger());

        $mollieSubscription = MollieSubscriptionBuilder::create()
            ->withStatus(SubscriptionStatus::COMPLETED)
            ->withNextPaymentDate(new \DateTimeImmutable('2099-06-01'))
            ->build()
        ;

        $action->execute(
            SubscriptionEntityBuilder::create()->build(),
            $mollieSubscription,
            new Payment('payment-id'),
            SubscriptionStatus::ACTIVE,
            CustomerBuilder::create()->build(),
            null,
            Context::createDefaultContext()
        );

        $events = $eventDispatcher->getEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(SubscriptionRenewedEvent::class, $events[0]);
        $this->assertInstanceOf(SubscriptionEndedEvent::class, $events[1]);
    }

    public function testExecuteTriggersAfterRenewalActionWhenProvided(): void
    {
        $actionHandler = new FakeSubscriptionActionHandler();
        $actionHandler->setResponse(MollieSubscriptionBuilder::create()->build());
        $action = new RenewAction(new FakeSubscriptionRepository(), new EventSpy(), $actionHandler, new NullLogger());

        $action->execute(
            SubscriptionEntityBuilder::create()->withId('subscription-id')->build(),
            MollieSubscriptionBuilder::create()->withNextPaymentDate(new \DateTimeImmutable('2099-06-01'))->build(),
            new Payment('payment-id'),
            SubscriptionStatus::CANCELED_AFTER_RENEWAL,
            CustomerBuilder::create()->build(),
            CancelAction::getActioName(),
            Context::createDefaultContext()
        );

        $this->assertSame(1, $actionHandler->getCallCount());
        $this->assertSame(CancelAction::getActioName(), $actionHandler->getCalls()[0]['action']);
        $this->assertSame('subscription-id', $actionHandler->getCalls()[0]['subscriptionId']);
    }

    public function testExecuteSwallowsExceptionFromAfterRenewalAction(): void
    {
        $actionHandler = new FakeSubscriptionActionHandler();
        $actionHandler->setException(new \RuntimeException('action handler exploded'));
        $action = new RenewAction(new FakeSubscriptionRepository(), new EventSpy(), $actionHandler, new NullLogger());

        $action->execute(
            SubscriptionEntityBuilder::create()->build(),
            MollieSubscriptionBuilder::create()->withNextPaymentDate(new \DateTimeImmutable('2099-06-01'))->build(),
            new Payment('payment-id'),
            SubscriptionStatus::PAUSED_AFTER_RENEWAL,
            CustomerBuilder::create()->build(),
            PauseAction::getActioName(),
            Context::createDefaultContext()
        );

        $this->assertSame(1, $actionHandler->getCallCount());
    }

    public function testExecuteSkipsAfterRenewalActionWhenNull(): void
    {
        $actionHandler = new FakeSubscriptionActionHandler();
        $action = new RenewAction(new FakeSubscriptionRepository(), new EventSpy(), $actionHandler, new NullLogger());

        $action->execute(
            SubscriptionEntityBuilder::create()->build(),
            MollieSubscriptionBuilder::create()->withNextPaymentDate(new \DateTimeImmutable('2099-06-01'))->build(),
            new Payment('payment-id'),
            SubscriptionStatus::ACTIVE,
            CustomerBuilder::create()->build(),
            null,
            Context::createDefaultContext()
        );

        $this->assertSame(0, $actionHandler->getCallCount());
    }
}
