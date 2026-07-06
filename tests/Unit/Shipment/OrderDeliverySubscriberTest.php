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

        $this->subscriber = new OrderDeliverySubscriber(
            $this->orderDeliveryRepository,
            $this->shipOrderRoute,
            $this->settingsService,
            new NullLogger(),
        );
    }

    public function testShipsWhenLatestTransactionIsMolliePayment(): void
    {
        $this->prepareDelivery($this->createOrder(new Payment('tr_fake_payment')));

        $this->subscriber->onOrderDeliveryChanged($this->createShipEvent());

        static::assertTrue($this->shipOrderRoute->wasCalled());
        static::assertSame('fakeshopwareorderid', $this->shipOrderRoute->getLastRequest()->get('orderId'));
    }

    public function testDoesNotShipWhenLatestTransactionIsNotMollie(): void
    {
        $this->prepareDelivery($this->createOrder(null));

        $this->subscriber->onOrderDeliveryChanged($this->createShipEvent());

        static::assertFalse($this->shipOrderRoute->wasCalled());
    }

    public function testDoesNotShipWhenAutomaticShipmentDisabled(): void
    {
        $this->settingsService = new FakeSettingsService(null, $this->createPaymentSettings(false));
        $this->subscriber = new OrderDeliverySubscriber(
            $this->orderDeliveryRepository,
            $this->shipOrderRoute,
            $this->settingsService,
            new NullLogger(),
        );

        $this->prepareDelivery($this->createOrder(new Payment('tr_fake_payment')));

        $this->subscriber->onOrderDeliveryChanged($this->createShipEvent());

        static::assertFalse($this->shipOrderRoute->wasCalled());
    }

    private function createPaymentSettings(bool $automaticShipment): PaymentSettings
    {
        return new PaymentSettings('', 0, false, false, false, false, false, $automaticShipment);
    }

    private function createOrder(?Payment $payment): OrderEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('fake-transaction-id');
        if ($payment !== null) {
            $transaction->addExtension(Mollie::EXTENSION, $payment);
        }

        $order = new OrderEntity();
        $order->setId('fakeshopwareorderid');
        $order->setOrderNumber('10000');
        $order->setSalesChannelId(TestDefaults::SALES_CHANNEL);
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

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
        $previousState = new StateMachineStateEntity();
        $previousState->setId('open-state-id');
        $previousState->setTechnicalName('open');

        $nextState = new StateMachineStateEntity();
        $nextState->setId('shipped-state-id');
        $nextState->setTechnicalName('shipped');

        $transition = new Transition(
            'order_delivery',
            self::DELIVERY_ID,
            StateMachineTransitionActions::ACTION_SHIP,
            'stateId',
        );

        return new StateMachineStateChangeEvent(
            Context::createDefaultContext(),
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            $transition,
            new StateMachineEntity(),
            $previousState,
            $nextState,
        );
    }
}
