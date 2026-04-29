<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Action;

use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Action\Exception\PauseAndResumeNotAllowedException;
use Mollie\Shopware\Component\Subscription\Action\Exception\SubscriptionNotActiveException;
use Mollie\Shopware\Component\Subscription\Action\PauseAction;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionPausedEvent;
use Mollie\Shopware\Component\Subscription\SubscriptionDataService;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGateway;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

#[CoversClass(PauseAction::class)]
final class PauseActionTest extends TestCase
{
    private const SUBSCRIPTION_ID = 'subscription-id';
    private const MOLLIE_SUBSCRIPTION_ID = 'sub_test123';
    private const ORDER_NUMBER = '10000';

    public function testExecutePausesImmediatelyWhenWithinCancelWindow(): void
    {
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();
        $mollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::MOLLIE_SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withNextPaymentDate(new \DateTimeImmutable('+30 days'))
            ->build();
        $gateway->register($mollieSubscription);

        $action = new PauseAction($repository, $gateway, new NullLogger());

        $result = $action->execute(
            $this->loadSubscriptionData($repository),
            new SubscriptionSettings(enabled: true, allowPauseAndResume: true, cancelDays: 5),
            $mollieSubscription,
            self::ORDER_NUMBER,
            $this->getContext()
        );

        $this->assertSame($mollieSubscription, $result);
        $this->assertSame(1, $gateway->getCallCount('cancelSubscription'));
        $this->assertSame(1, $repository->getUpsertCount());
        $payload = $repository->getLastUpsert();
        $this->assertSame(SubscriptionStatus::PAUSED->value, $payload['status']);
        $this->assertInstanceOf(\DateTimeInterface::class, $payload['canceledAt']);
        $this->assertNull($payload['nextPaymentAt']);
        $this->assertSame('paused', $payload['historyEntries'][0]['comment']);
    }

    public function testExecuteSchedulesDeferredPauseWhenOutsideCancelWindow(): void
    {
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();
        $nextPaymentDate = new \DateTimeImmutable('+1 day');
        $mollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::MOLLIE_SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withNextPaymentDate($nextPaymentDate)
            ->build();
        $gateway->register($mollieSubscription);

        $action = new PauseAction($repository, $gateway, new NullLogger());

        $result = $action->execute(
            $this->loadSubscriptionData($repository),
            new SubscriptionSettings(enabled: true, allowPauseAndResume: true, cancelDays: 5),
            $mollieSubscription,
            self::ORDER_NUMBER,
            $this->getContext()
        );

        $this->assertSame($mollieSubscription, $result);
        $this->assertSame(0, $gateway->getCallCount('cancelSubscription'));
        $payload = $repository->getLastUpsert();
        $this->assertSame(SubscriptionStatus::PAUSED_AFTER_RENEWAL->value, $payload['status']);
        $this->assertNull($payload['canceledAt']);
        $this->assertSame($nextPaymentDate, $payload['nextPaymentAt']);
        $this->assertStringStartsWith('paused after ', $payload['historyEntries'][0]['comment']);
    }

    public function testExecuteThrowsWhenPauseAndResumeIsNotAllowed(): void
    {
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();
        $mollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::MOLLIE_SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->build();
        $gateway->register($mollieSubscription);

        $action = new PauseAction($repository, $gateway, new NullLogger());

        $this->expectException(PauseAndResumeNotAllowedException::class);

        try {
            $action->execute(
                $this->loadSubscriptionData($repository),
                new SubscriptionSettings(enabled: true, allowPauseAndResume: false),
                $mollieSubscription,
                self::ORDER_NUMBER,
                $this->getContext()
            );
        } finally {
            $this->assertSame(0, $repository->getUpsertCount());
            $this->assertSame(0, $gateway->getCallCount('cancelSubscription'));
        }
    }

    public function testExecuteThrowsWhenMollieSubscriptionIsNotActive(): void
    {
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();
        $mollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::MOLLIE_SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::COMPLETED)
            ->build();
        $gateway->register($mollieSubscription);

        $action = new PauseAction($repository, $gateway, new NullLogger());

        $this->expectException(SubscriptionNotActiveException::class);

        try {
            $action->execute(
                $this->loadSubscriptionData($repository),
                new SubscriptionSettings(enabled: true, allowPauseAndResume: true),
                $mollieSubscription,
                self::ORDER_NUMBER,
                $this->getContext()
            );
        } finally {
            $this->assertSame(0, $repository->getUpsertCount());
            $this->assertSame(0, $gateway->getCallCount('cancelSubscription'));
        }
    }

    public function testGetEventClassReturnsPausedEvent(): void
    {
        $action = new PauseAction(new FakeSubscriptionRepository(), new FakeSubscriptionGateway(), new NullLogger());

        $this->assertSame(SubscriptionPausedEvent::class, $action->getEventClass());
    }

    public function testActionNameIsPause(): void
    {
        $this->assertSame('pause', PauseAction::getActioName());
    }

    private function prepareRepositoryWithSubscription(): FakeSubscriptionRepository
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId(self::SUBSCRIPTION_ID)
            ->withMollieId(self::MOLLIE_SUBSCRIPTION_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->build();

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        return $repository;
    }

    private function loadSubscriptionData(FakeSubscriptionRepository $repository): \Mollie\Shopware\Component\Subscription\SubscriptionDataStruct
    {
        return (new SubscriptionDataService($repository, new NullLogger()))->findById(self::SUBSCRIPTION_ID, $this->getContext());
    }

    private function getContext(): Context
    {
        return new Context(new SystemSource());
    }
}
