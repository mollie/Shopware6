<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Action;

use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Action\Exception\NextPaymentAtNotFoundException;
use Mollie\Shopware\Component\Subscription\Action\Exception\PauseAndResumeNotAllowedException;
use Mollie\Shopware\Component\Subscription\Action\Exception\SubscriptionNotActiveException;
use Mollie\Shopware\Component\Subscription\Action\SkipAction;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionSkippedEvent;
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

#[CoversClass(SkipAction::class)]
final class SkipActionTest extends TestCase
{
    private const SUBSCRIPTION_ID = 'subscription-id';
    private const OLD_MOLLIE_ID = 'sub_old123';
    private const NEW_MOLLIE_ID = 'sub_new456';
    private const ORDER_NUMBER = '10000';

    public function testExecuteSkipsImmediatelyWhenWithinCancelWindow(): void
    {
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();

        $oldMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::OLD_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withNextPaymentDate(new \DateTime('+30 days'))
            ->build();
        $newNextPaymentDate = new \DateTime('+60 days');
        $newMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::NEW_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withNextPaymentDate($newNextPaymentDate)
            ->build();
        $gateway->register($oldMollieSubscription);
        $gateway->setCopyResponse($newMollieSubscription);

        $action = new SkipAction($repository, $gateway, new NullLogger());

        $action->execute(
            $this->loadSubscriptionData($repository),
            new SubscriptionSettings(enabled: true, allowPauseAndResume: true, cancelDays: 5),
            $oldMollieSubscription,
            self::ORDER_NUMBER,
            $this->getContext()
        );

        $this->assertSame(1, $gateway->getCallCount('cancelSubscription'));
        $this->assertSame(1, $gateway->getCallCount('copySubscription'));
        $payload = $repository->getLastUpsert();
        $this->assertSame(SubscriptionStatus::SKIPPED->value, $payload['status']);
        $this->assertSame(self::NEW_MOLLIE_ID, $payload['mollieId']);
        $this->assertSame($newNextPaymentDate, $payload['nextPaymentAt']);
        $this->assertNull($payload['canceledAt']);
        $this->assertSame('skipped', $payload['historyEntries'][0]['comment']);
        $this->assertSame(self::NEW_MOLLIE_ID, $payload['historyEntries'][0]['mollieId']);
    }

    public function testExecuteSchedulesDeferredSkipWhenOutsideCancelWindow(): void
    {
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();

        $nextPaymentDate = new \DateTime('+1 day');
        $oldMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::OLD_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withNextPaymentDate($nextPaymentDate)
            ->build();
        $gateway->register($oldMollieSubscription);

        $action = new SkipAction($repository, $gateway, new NullLogger());

        $action->execute(
            $this->loadSubscriptionData($repository),
            new SubscriptionSettings(enabled: true, allowPauseAndResume: true, cancelDays: 5),
            $oldMollieSubscription,
            self::ORDER_NUMBER,
            $this->getContext()
        );

        $this->assertSame(0, $gateway->getCallCount('cancelSubscription'));
        $this->assertSame(0, $gateway->getCallCount('copySubscription'));
        $payload = $repository->getLastUpsert();
        $this->assertSame(SubscriptionStatus::SKIPPED_AFTER_RENEWAL->value, $payload['status']);
        $this->assertSame(self::OLD_MOLLIE_ID, $payload['mollieId']);
        $this->assertSame($nextPaymentDate, $payload['nextPaymentAt']);
        $this->assertNull($payload['canceledAt']);
        $this->assertStringStartsWith('skipped after ', $payload['historyEntries'][0]['comment']);
    }

    public function testExecuteThrowsWhenNewSubscriptionHasNoNextPaymentDate(): void
    {
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();

        $oldMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::OLD_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withNextPaymentDate(new \DateTime('+30 days'))
            ->build();
        $newMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::NEW_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->build();
        $gateway->register($oldMollieSubscription);
        $gateway->setCopyResponse($newMollieSubscription);

        $action = new SkipAction($repository, $gateway, new NullLogger());

        $this->expectException(NextPaymentAtNotFoundException::class);

        try {
            $action->execute(
                $this->loadSubscriptionData($repository),
                new SubscriptionSettings(enabled: true, allowPauseAndResume: true, cancelDays: 5),
                $oldMollieSubscription,
                self::ORDER_NUMBER,
                $this->getContext()
            );
        } finally {
            $this->assertSame(0, $repository->getUpsertCount());
        }
    }

    public function testExecuteThrowsWhenPauseAndResumeIsNotAllowed(): void
    {
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();
        $oldMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::OLD_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->build();
        $gateway->register($oldMollieSubscription);

        $action = new SkipAction($repository, $gateway, new NullLogger());

        $this->expectException(PauseAndResumeNotAllowedException::class);

        try {
            $action->execute(
                $this->loadSubscriptionData($repository),
                new SubscriptionSettings(enabled: true, allowPauseAndResume: false),
                $oldMollieSubscription,
                self::ORDER_NUMBER,
                $this->getContext()
            );
        } finally {
            $this->assertSame(0, $repository->getUpsertCount());
            $this->assertSame(0, $gateway->getCallCount('cancelSubscription'));
            $this->assertSame(0, $gateway->getCallCount('copySubscription'));
        }
    }

    public function testExecuteThrowsWhenMollieSubscriptionIsNotActive(): void
    {
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();
        $oldMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::OLD_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::COMPLETED)
            ->build();
        $gateway->register($oldMollieSubscription);

        $action = new SkipAction($repository, $gateway, new NullLogger());

        $this->expectException(SubscriptionNotActiveException::class);

        try {
            $action->execute(
                $this->loadSubscriptionData($repository),
                new SubscriptionSettings(enabled: true, allowPauseAndResume: true),
                $oldMollieSubscription,
                self::ORDER_NUMBER,
                $this->getContext()
            );
        } finally {
            $this->assertSame(0, $repository->getUpsertCount());
            $this->assertSame(0, $gateway->getCallCount('cancelSubscription'));
            $this->assertSame(0, $gateway->getCallCount('copySubscription'));
        }
    }

    public function testGetEventClassReturnsSkippedEvent(): void
    {
        $action = new SkipAction(new FakeSubscriptionRepository(), new FakeSubscriptionGateway(), new NullLogger());

        $this->assertSame(SubscriptionSkippedEvent::class, $action->getEventClass());
    }

    public function testActionNameIsSkip(): void
    {
        $this->assertSame('skip', SkipAction::getActioName());
    }

    private function prepareRepositoryWithSubscription(): FakeSubscriptionRepository
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId(self::SUBSCRIPTION_ID)
            ->withMollieId(self::OLD_MOLLIE_ID)
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
