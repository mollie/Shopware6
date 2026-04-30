<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Action;

use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Action\Exception\PauseAndResumeNotAllowedException;
use Mollie\Shopware\Component\Subscription\Action\Exception\SubscriptionActiveException;
use Mollie\Shopware\Component\Subscription\Action\ResumeAction;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionResumedEvent;
use Mollie\Shopware\Component\Subscription\SubscriptionDataService;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGateway;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;

#[CoversClass(ResumeAction::class)]
#[CoversClass(PauseAndResumeNotAllowedException::class)]
#[CoversClass(SubscriptionActiveException::class)]
final class ResumeActionTest extends TestCase
{
    private const SUBSCRIPTION_ID = 'subscription-id';
    private const OLD_MOLLIE_ID = 'sub_old123';
    private const NEW_MOLLIE_ID = 'sub_new456';
    private const ORDER_NUMBER = '10000';

    public function testExecuteCopiesSubscriptionAndPersistsResumedState(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();
        $oldMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::OLD_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::CANCELED)
            ->build();
        $newMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::NEW_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withNextPaymentDate(new \DateTimeImmutable('+30 days'))
            ->build();
        $gateway->register($oldMollieSubscription);
        $gateway->setCopyResponse($newMollieSubscription);

        $action = new ResumeAction($repository, $gateway, new NullLogger());

        $result = $action->execute(
            $this->loadSubscriptionData($repository, $context),
            new SubscriptionSettings(enabled: true, allowPauseAndResume: true),
            $oldMollieSubscription,
            self::ORDER_NUMBER,
            $context
        );

        $this->assertSame($newMollieSubscription, $result);
        $this->assertSame(1, $gateway->getCallCount('copySubscription'));
        $this->assertSame(1, $repository->getUpsertCount());
        $payload = $repository->getLastUpsert();
        $this->assertSame(SubscriptionStatus::RESUMED->value, $payload['status']);
        $this->assertSame(self::NEW_MOLLIE_ID, $payload['mollieId']);
        $this->assertSame($newMollieSubscription->getNextPaymentDate(), $payload['nextPaymentAt']);
        $this->assertNull($payload['canceledAt']);
        $this->assertSame('resumed', $payload['historyEntries'][0]['comment']);
    }

    public function testExecuteUsesNextPossiblePaymentDateWhenInTheFuture(): void
    {
        $context = Context::createDefaultContext();
        $futureDate = (new \DateTime('+30 days'))->format('Y-m-d');
        $repository = $this->prepareRepositoryWithSubscription(
            new SubscriptionMetadata('2026-01-01', 1, IntervalUnit::MONTHS, 0, '', $futureDate)
        );
        $gateway = new FakeSubscriptionGateway();
        $oldMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::OLD_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::CANCELED)
            ->build();
        $newMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::NEW_MOLLIE_ID)
            ->build();
        $gateway->register($oldMollieSubscription);
        $gateway->setCopyResponse($newMollieSubscription);

        $action = new ResumeAction($repository, $gateway, new NullLogger());

        $action->execute(
            $this->loadSubscriptionData($repository, $context),
            new SubscriptionSettings(enabled: true, allowPauseAndResume: true),
            $oldMollieSubscription,
            self::ORDER_NUMBER,
            $context
        );

        $this->assertSame($futureDate, $oldMollieSubscription->getStartDate()->format('Y-m-d'));
    }

    public function testExecuteFallsBackToTodayWhenNextPossiblePaymentDateIsInThePast(): void
    {
        $context = Context::createDefaultContext();
        $pastDate = (new \DateTime('-10 days'))->format('Y-m-d');
        $today = (new \DateTime())->format('Y-m-d');
        $repository = $this->prepareRepositoryWithSubscription(
            new SubscriptionMetadata('2026-01-01', 1, IntervalUnit::MONTHS, 0, '', $pastDate)
        );
        $gateway = new FakeSubscriptionGateway();
        $oldMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::OLD_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::CANCELED)
            ->build();
        $newMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::NEW_MOLLIE_ID)
            ->build();
        $gateway->register($oldMollieSubscription);
        $gateway->setCopyResponse($newMollieSubscription);

        $action = new ResumeAction($repository, $gateway, new NullLogger());

        $action->execute(
            $this->loadSubscriptionData($repository, $context),
            new SubscriptionSettings(enabled: true, allowPauseAndResume: true),
            $oldMollieSubscription,
            self::ORDER_NUMBER,
            $context
        );

        $this->assertSame($today, $oldMollieSubscription->getStartDate()->format('Y-m-d'));
    }

    public function testExecuteThrowsWhenPauseAndResumeIsNotAllowed(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();
        $oldMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::OLD_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::CANCELED)
            ->build();
        $gateway->register($oldMollieSubscription);

        $action = new ResumeAction($repository, $gateway, new NullLogger());

        $this->expectException(PauseAndResumeNotAllowedException::class);

        try {
            $action->execute(
                $this->loadSubscriptionData($repository, $context),
                new SubscriptionSettings(enabled: true, allowPauseAndResume: false),
                $oldMollieSubscription,
                self::ORDER_NUMBER,
                $context
            );
        } finally {
            $this->assertSame(0, $repository->getUpsertCount());
            $this->assertSame(0, $gateway->getCallCount('copySubscription'));
        }
    }

    public function testExecuteThrowsWhenMollieSubscriptionIsActive(): void
    {
        $context = Context::createDefaultContext();
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = new FakeSubscriptionGateway();
        $oldMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::OLD_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->build();
        $gateway->register($oldMollieSubscription);

        $action = new ResumeAction($repository, $gateway, new NullLogger());

        $this->expectException(SubscriptionActiveException::class);

        try {
            $action->execute(
                $this->loadSubscriptionData($repository, $context),
                new SubscriptionSettings(enabled: true, allowPauseAndResume: true),
                $oldMollieSubscription,
                self::ORDER_NUMBER,
                $context
            );
        } finally {
            $this->assertSame(0, $repository->getUpsertCount());
            $this->assertSame(0, $gateway->getCallCount('copySubscription'));
        }
    }

    public function testGetEventClassReturnsResumedEvent(): void
    {
        $action = new ResumeAction(new FakeSubscriptionRepository(), new FakeSubscriptionGateway(), new NullLogger());

        $this->assertSame(SubscriptionResumedEvent::class, $action->getEventClass());
    }

    public function testActionNameIsResume(): void
    {
        $this->assertSame('resume', ResumeAction::getActioName());
    }

    private function prepareRepositoryWithSubscription(?SubscriptionMetadata $metadata = null): FakeSubscriptionRepository
    {
        $builder = SubscriptionEntityBuilder::create()
            ->withId(self::SUBSCRIPTION_ID)
            ->withMollieId(self::OLD_MOLLIE_ID)
            ->withStatus(SubscriptionStatus::PAUSED);

        if ($metadata instanceof SubscriptionMetadata) {
            $builder = $builder->withMetadata($metadata);
        }

        $repository = new FakeSubscriptionRepository();
        $repository->add($builder->build());

        return $repository;
    }

    private function loadSubscriptionData(FakeSubscriptionRepository $repository, Context $context): \Mollie\Shopware\Component\Subscription\SubscriptionDataStruct
    {
        return (new SubscriptionDataService($repository, new NullLogger()))->findById(self::SUBSCRIPTION_ID, $context);
    }
}
