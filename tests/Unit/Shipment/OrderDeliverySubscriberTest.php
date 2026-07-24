<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Shipment;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Component\Shipment\OrderDeliverySubscriber;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\FakeEntityRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Fake\FakeShipOrderRoute;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\Test\TestDefaults;

class OrderDeliverySubscriberTest extends TestCase
{
    private const DELIVERY_ID = 'fake-delivery-id';

    private FakeEntityRepository $orderDeliveryRepository;

    private FakeShipOrderRoute $shipOrderRoute;

    private FakeSettingsService $settingsService;

    private OrderDeliverySubscriber $subscriber;

    protected function setUp(): void
    {
        $this->orderDeliveryRepository = new FakeEntityRepository(new OrderDeliveryDefinition());
        $this->shipOrderRoute = new FakeShipOrderRoute();
        $this->settingsService = new FakeSettingsService(null, $this->createPaymentSettings(true));

        $this->subscriber = $this->createSubscriber();
    }

    public function testDelegatesToShipRouteWhenAutomaticShipmentEnabled(): void
    {
        $this->prepareDelivery($this->createOrder());

        $this->subscriber->onOrderDeliveryChanged($this->createShipEvent());

        static::assertTrue($this->shipOrderRoute->wasCalled());
        static::assertSame('fakeshopwareorderid', $this->shipOrderRoute->getLastRequest()->get('orderId'));
    }

    public function testDoesNotShipWhenAutomaticShipmentDisabled(): void
    {
        $this->settingsService = new FakeSettingsService(null, $this->createPaymentSettings(false));
        $this->subscriber = $this->createSubscriber();

        $this->prepareDelivery($this->createOrder());

        $this->subscriber->onOrderDeliveryChanged($this->createShipEvent());

        static::assertFalse($this->shipOrderRoute->wasCalled());
    }

    public function testIgnoresTransitionsThatAreNotShip(): void
    {
        $this->prepareDelivery($this->createOrder());

        $this->subscriber->onOrderDeliveryChanged($this->createEvent(
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            StateMachineTransitionActions::ACTION_CANCEL,
        ));

        static::assertFalse($this->shipOrderRoute->wasCalled());
    }

    public function testIgnoresLeaveTransitionSide(): void
    {
        $this->prepareDelivery($this->createOrder());

        $this->subscriber->onOrderDeliveryChanged($this->createEvent(
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE,
            StateMachineTransitionActions::ACTION_SHIP,
        ));

        static::assertFalse($this->shipOrderRoute->wasCalled());
    }

    /**
     * A failing shipment must not bubble up into the delivery state machine transition and break the
     * admin action, so the subscriber swallows the exception.
     */
    public function testShipmentExceptionIsSwallowed(): void
    {
        $this->shipOrderRoute->setShouldThrow(true);
        $this->prepareDelivery($this->createOrder());

        $this->subscriber->onOrderDeliveryChanged($this->createShipEvent());

        static::assertTrue($this->shipOrderRoute->wasCalled());
    }

    private function createSubscriber(): OrderDeliverySubscriber
    {
        return new OrderDeliverySubscriber(
            $this->orderDeliveryRepository,
            $this->shipOrderRoute,
            $this->settingsService,
            new NullLogger(),
        );
    }

    private function createPaymentSettings(bool $automaticShipment): PaymentSettings
    {
        return new PaymentSettings('', 0, false, false, false, false, false, $automaticShipment);
    }

    private function createOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('fakeshopwareorderid');
        $order->setOrderNumber('10000');
        $order->setSalesChannelId(TestDefaults::SALES_CHANNEL);

        $state = new StateMachineStateEntity();
        $state->setId('paid-state-id');
        $state->setTechnicalName('paid');

        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId('fakeshopwaretransactionid');
        $orderTransaction->setOrderId($order->getId());
        $orderTransaction->setStateMachineState($state);
        $orderTransaction->setCustomFields([
            Mollie::EXTENSION => new Payment('fake-mollie-payment-id'),
        ]);

        $order->setTransactions(new OrderTransactionCollection([$orderTransaction]));

        return $order;
    }

    private function prepareDelivery(OrderEntity $order): void
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId(self::DELIVERY_ID);
        $delivery->setOrder($order);

        $context = Context::createDefaultContext();
        $this->orderDeliveryRepository->entitySearchResults[] = new EntitySearchResult(
            OrderDeliveryEntity::class,
            1,
            new OrderDeliveryCollection([$delivery]),
            null,
            new Criteria([self::DELIVERY_ID]),
            $context,
        );
    }

    private function createShipEvent(): StateMachineStateChangeEvent
    {
        return $this->createEvent(
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            StateMachineTransitionActions::ACTION_SHIP,
        );
    }

    private function createEvent(string $transitionSide, string $transitionName): StateMachineStateChangeEvent
    {
        $previousState = new StateMachineStateEntity();
        $previousState->setId('open-state-id');
        $previousState->setTechnicalName('open');

        $nextState = new StateMachineStateEntity();
        $nextState->setId('shipped-state-id');
        $nextState->setTechnicalName('shipped');

        $transition = new Transition(
            'order_delivery',
            self::DELIVERY_ID,
            $transitionName,
            'stateId',
        );

        return new StateMachineStateChangeEvent(
            Context::createDefaultContext(),
            $transitionSide,
            $transition,
            new StateMachineEntity(),
            $previousState,
            $nextState,
        );
    }
}
