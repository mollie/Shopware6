<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionCancelledEvent;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionPausedEvent;
use Mollie\Shopware\Component\Subscription\Exception\SubscriptionDisabledException;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandler;
use Mollie\Shopware\Component\Subscription\SubscriptionDataService;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeAction;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGateway;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;

#[CoversClass(SubscriptionActionHandler::class)]
#[CoversClass(SubscriptionDisabledException::class)]
final class SubscriptionActionHandlerTest extends TestCase
{
    private const SUBSCRIPTION_ID = 'subscription-id';
    private const MOLLIE_SUBSCRIPTION_ID = 'sub_test123';

    public function testHandleExecutesMatchingActionAndDispatchesItsEvent(): void
    {
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = $this->prepareGatewayWithMollieSubscription();
        $eventDispatcher = new EventSpy();

        $cancelAction = new FakeAction('cancel', SubscriptionCancelledEvent::class);
        $pauseAction = new FakeAction('pause', SubscriptionPausedEvent::class);

        $handler = $this->getHandler($repository, $gateway, $eventDispatcher, [$cancelAction, $pauseAction], enabled: true);

        $result = $handler->handle('cancel', self::SUBSCRIPTION_ID, Context::createDefaultContext());

        $this->assertSame(self::MOLLIE_SUBSCRIPTION_ID, $result->getId());
        $this->assertSame(1, $cancelAction->getExecutionCount());
        $this->assertSame(self::SUBSCRIPTION_ID, $cancelAction->getExecutions()[0]['subscriptionId']);
        $this->assertSame(0, $pauseAction->getExecutionCount());
        $this->assertInstanceOf(SubscriptionCancelledEvent::class, $eventDispatcher->getEvent());
    }

    public function testHandleNormalizesSubscriptionIdToLowerCase(): void
    {
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = $this->prepareGatewayWithMollieSubscription();

        $cancelAction = new FakeAction('cancel');
        $handler = $this->getHandler($repository, $gateway, new EventSpy(), [$cancelAction], enabled: true);

        $handler->handle('cancel', strtoupper(self::SUBSCRIPTION_ID), Context::createDefaultContext());

        $this->assertSame(self::SUBSCRIPTION_ID, $cancelAction->getExecutions()[0]['subscriptionId']);
    }

    public function testHandleThrowsWhenSubscriptionsAreDisabledForSalesChannel(): void
    {
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = $this->prepareGatewayWithMollieSubscription();

        $handler = $this->getHandler($repository, $gateway, new EventSpy(), [new FakeAction('cancel')], enabled: false);

        $this->expectException(SubscriptionDisabledException::class);

        $handler->handle('cancel', self::SUBSCRIPTION_ID, Context::createDefaultContext());
    }

    public function testHandleThrowsWhenNoActionMatches(): void
    {
        $repository = $this->prepareRepositoryWithSubscription();
        $gateway = $this->prepareGatewayWithMollieSubscription();

        $handler = $this->getHandler($repository, $gateway, new EventSpy(), [new FakeAction('pause')], enabled: true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No action handler found for action: cancel');

        $handler->handle('cancel', self::SUBSCRIPTION_ID, Context::createDefaultContext());
    }

    public function testGetActionEventsReturnsEventClassesOfRegisteredActions(): void
    {
        $repository = new FakeSubscriptionRepository();
        $gateway = new FakeSubscriptionGateway();

        $handler = $this->getHandler(
            $repository,
            $gateway,
            new EventSpy(),
            [
                new FakeAction('cancel', SubscriptionCancelledEvent::class),
                new FakeAction('pause', SubscriptionPausedEvent::class),
            ],
            enabled: true
        );

        $this->assertSame(
            [SubscriptionCancelledEvent::class, SubscriptionPausedEvent::class],
            $handler->getActionEvents()
        );
    }

    private function prepareRepositoryWithSubscription(): FakeSubscriptionRepository
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId(self::SUBSCRIPTION_ID)
            ->build();
        $subscription->setMollieId(self::MOLLIE_SUBSCRIPTION_ID);

        $repository = new FakeSubscriptionRepository();
        $repository->add($subscription);

        return $repository;
    }

    private function prepareGatewayWithMollieSubscription(): FakeSubscriptionGateway
    {
        $mollieSubscription = MollieSubscriptionBuilder::create()
            ->withId(self::MOLLIE_SUBSCRIPTION_ID)
            ->build();

        $gateway = new FakeSubscriptionGateway();
        $gateway->register($mollieSubscription);

        return $gateway;
    }

    /**
     * @param iterable<\Mollie\Shopware\Component\Subscription\Action\AbstractAction> $actions
     */
    private function getHandler(
        FakeSubscriptionRepository $repository,
        FakeSubscriptionGateway $gateway,
        EventSpy $eventDispatcher,
        iterable $actions,
        bool $enabled
    ): SubscriptionActionHandler {
        $logger = new NullLogger();
        $dataService = new SubscriptionDataService($repository, $logger);
        $settingsService = new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: $enabled));

        return new SubscriptionActionHandler(
            $settingsService,
            $gateway,
            $dataService,
            $actions,
            $eventDispatcher,
            $logger
        );
    }

}
