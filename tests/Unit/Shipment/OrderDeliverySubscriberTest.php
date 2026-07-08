<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Shipment;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Component\Shipment\OrderDeliverySubscriber;
use Mollie\Shopware\Component\Transaction\OrderTransactionResolver;
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
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
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

    private OrderTransactionResolver $transactionResolver;

    private OrderDeliverySubscriber $subscriber;

    protected function setUp(): void
    {
        $this->orderDeliveryRepository = new FakeEntityRepository(new OrderDeliveryDefinition());
        $this->shipOrderRoute = new FakeShipOrderRoute();
        $this->settingsService = new FakeSettingsService(null, $this->createPaymentSettings(true));
        $this->transactionResolver = new OrderTransactionResolver();

        $this->subscriber = new OrderDeliverySubscriber(
            $this->orderDeliveryRepository,
            $this->shipOrderRoute,
            $this->settingsService,
            $this->transactionResolver,
            new NullLogger(),
        );
    }

    public function testShipsWhenLatestAuthorizedTransactionIsMollie(): void
    {
        $this->prepareDelivery($this->createOrder(new Payment('tr_fake_payment'), OrderTransactionStates::STATE_AUTHORIZED));

        $this->subscriber->onOrderDeliveryChanged($this->createShipEvent());

        static::assertTrue($this->shipOrderRoute->wasCalled());
        static::assertSame('fakeshopwareorderid', $this->shipOrderRoute->getLastRequest()->get('orderId'));
    }

    /**
     * A paid payment is already captured, so nothing is shipped even when an authorized Mollie
     * transaction still exists next to the paid one.
     */
    public function testDoesNotShipWhenAPaidTransactionExists(): void
    {
        $authorizedMollie = $this->createTransaction('authorized-mollie-transaction-id', new Payment('tr_fake_payment'), OrderTransactionStates::STATE_AUTHORIZED, 1000);
        $paid = $this->createTransaction('paid-transaction-id', null, OrderTransactionStates::STATE_PAID, 2000);

        $this->prepareDelivery($this->createOrderWith($paid, $authorizedMollie));

        $this->subscriber->onOrderDeliveryChanged($this->createShipEvent());

        static::assertFalse($this->shipOrderRoute->wasCalled());
    }

    public function testDoesNotShipWhenNoAuthorizedTransactionExists(): void
    {
        $this->prepareDelivery($this->createOrder(new Payment('tr_fake_payment'), OrderTransactionStates::STATE_OPEN));

        $this->subscriber->onOrderDeliveryChanged($this->createShipEvent());

        static::assertFalse($this->shipOrderRoute->wasCalled());
    }

    /**
     * The order was completed with another (non-Mollie) authorized payment, so the latest authorized
     * transaction is not a Mollie payment. We must not ship via Mollie then, even though an older Mollie
     * transaction is still authorized.
     */
    public function testDoesNotShipWhenLatestAuthorizedTransactionIsNotMollie(): void
    {
        $olderMollie = $this->createTransaction('older-mollie-transaction-id', new Payment('tr_fake_payment'), OrderTransactionStates::STATE_AUTHORIZED, 1000);
        $newerNonMollie = $this->createTransaction('newer-non-mollie-transaction-id', null, OrderTransactionStates::STATE_AUTHORIZED, 2000);

        $this->prepareDelivery($this->createOrderWith($olderMollie, $newerNonMollie));

        $this->subscriber->onOrderDeliveryChanged($this->createShipEvent());

        static::assertFalse($this->shipOrderRoute->wasCalled());
    }

    /**
     * When several authorized transactions exist, the latest one is used. Here the newest is the Mollie
     * payment, so we ship.
     */
    public function testShipsUsingLatestAuthorizedMollieTransaction(): void
    {
        $olderNonMollie = $this->createTransaction('older-non-mollie-transaction-id', null, OrderTransactionStates::STATE_AUTHORIZED, 1000);
        $newerMollie = $this->createTransaction('newer-mollie-transaction-id', new Payment('tr_fake_payment'), OrderTransactionStates::STATE_AUTHORIZED, 2000);

        $this->prepareDelivery($this->createOrderWith($olderNonMollie, $newerMollie));

        $this->subscriber->onOrderDeliveryChanged($this->createShipEvent());

        static::assertTrue($this->shipOrderRoute->wasCalled());
    }

    /**
     * A failing shipment must not bubble up into the delivery state machine transition and break the
     * admin action, so the subscriber swallows the exception.
     */
    public function testShipmentExceptionIsSwallowed(): void
    {
        $this->shipOrderRoute->setShouldThrow(true);
        $this->prepareDelivery($this->createOrder(new Payment('tr_fake_payment'), OrderTransactionStates::STATE_AUTHORIZED));

        $this->subscriber->onOrderDeliveryChanged($this->createShipEvent());

        static::assertTrue($this->shipOrderRoute->wasCalled());
    }

    public function testDoesNotShipWhenAutomaticShipmentDisabled(): void
    {
        $this->settingsService = new FakeSettingsService(null, $this->createPaymentSettings(false));
        $this->subscriber = new OrderDeliverySubscriber(
            $this->orderDeliveryRepository,
            $this->shipOrderRoute,
            $this->settingsService,
            $this->transactionResolver,
            new NullLogger(),
        );

        $this->prepareDelivery($this->createOrder(new Payment('tr_fake_payment'), OrderTransactionStates::STATE_AUTHORIZED));

        $this->subscriber->onOrderDeliveryChanged($this->createShipEvent());

        static::assertFalse($this->shipOrderRoute->wasCalled());
    }

    private function createPaymentSettings(bool $automaticShipment): PaymentSettings
    {
        return new PaymentSettings('', 0, false, false, false, false, false, $automaticShipment);
    }

    private function createOrder(?Payment $payment, string $state): OrderEntity
    {
        return $this->createOrderWith($this->createTransaction('fake-transaction-id', $payment, $state, 1000));
    }

    private function createOrderWith(OrderTransactionEntity ...$transactions): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('fakeshopwareorderid');
        $order->setOrderNumber('10000');
        $order->setSalesChannelId(TestDefaults::SALES_CHANNEL);
        $order->setTransactions(new OrderTransactionCollection($transactions));

        return $order;
    }

    private function createTransaction(string $id, ?Payment $payment, string $state, int $createdAtTimestamp): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId($id);
        $transaction->setCreatedAt((new \DateTimeImmutable())->setTimestamp($createdAtTimestamp));

        $stateEntity = new StateMachineStateEntity();
        $stateEntity->setId($state . '-state-id');
        $stateEntity->setTechnicalName($state);
        $transaction->setStateMachineState($stateEntity);

        if ($payment !== null) {
            $transaction->addExtension(Mollie::EXTENSION, $payment);
        }

        return $transaction;
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
