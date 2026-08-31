<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Action;

use Mollie\Shopware\Component\FlowBuilder\Action\RefundOrderAction;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Refund\CreditNoteService;
use Mollie\Shopware\Component\Refund\DAL\Order\OrderExtension;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundCollection;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundDefinition;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Mollie\Shopware\Component\Refund\RefundableTotalCalculator;
use Mollie\Shopware\Component\Refund\RefundCompositionBuilder;
use Mollie\Shopware\Component\Refund\RefundItemSplitter;
use Mollie\Shopware\Component\Refund\RefundOrderLoader;
use Mollie\Shopware\Component\Refund\RefundPersister;
use Mollie\Shopware\Component\Refund\RefundTotalsBuilder;
use Mollie\Shopware\Component\Refund\Route\CreateRefundRoute;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeEntityRepository;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeRecalculationService;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Payment\Fake\FakeRefundGateway;
use Mollie\Shopware\Unit\Refund\Fake\FakeRefundBuilder;
use Mollie\Shopware\Unit\Refund\Fake\FakeStockStorage;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Symfony\Component\DependencyInjection\Container;

/**
 * The flow action refunds the whole order. It only unpacks the order id from the flow and hands it
 * to the regular refund controller, with a description that tells the merchant where it came from.
 */
#[CoversClass(RefundOrderAction::class)]
final class RefundOrderActionTest extends TestCase
{
    private const ORDER_ID = 'order-1';

    private Context $context;

    private FakeOrderSearchRepository $orderRepository;

    private FakeRefundBuilder $refundBuilder;

    private FakeRefundGateway $refundGateway;

    private FakeEntityRepository $refundRepository;

    protected function setUp(): void
    {
        $this->context = new Context(new SystemSource());
        $this->orderRepository = new FakeOrderSearchRepository();
        $this->refundBuilder = new FakeRefundBuilder();
        $this->refundGateway = new FakeRefundGateway();
        $this->refundRepository = new FakeEntityRepository(new RefundDefinition());
    }

    public function testTheActionIsOfferedUnderItsOwnName(): void
    {
        $this->assertSame('action.mollie.order.refund', RefundOrderAction::getName());
    }

    public function testTheActionRunsWhenTheFlowFiresIt(): void
    {
        $this->assertArrayHasKey(RefundOrderAction::getName(), RefundOrderAction::getSubscribedEvents());
    }

    /**
     * Without an order there is nothing to refund, so the Flow Builder must not offer the action on
     * a flow that carries none.
     */
    public function testTheActionIsOnlyOfferedOnFlowsThatCarryAnOrder(): void
    {
        $this->assertSame([OrderAware::class], $this->action()->requirements());
    }

    public function testTheOrderFromTheFlowIsRefundedAtMollie(): void
    {
        $this->givenOrder();

        $this->action()->handleFlow($this->flow(self::ORDER_ID));

        $this->assertCount(1, $this->refundGateway->getCreatedRefunds());
    }

    /**
     * A flow refunds the whole order: no line items and no amount means "everything".
     */
    public function testTheWholeOrderIsRefundedNotSingleLines(): void
    {
        $this->givenOrder();

        $this->action()->handleFlow($this->flow(self::ORDER_ID));

        $call = $this->refundBuilder->getLastCall();
        $this->assertSame([], $call['items']);
        $this->assertNull($call['amount']);
    }

    /**
     * The description reaches the customer's bank statement, so it has to say where the refund came
     * from instead of staying empty.
     */
    public function testTheRefundSaysItCameFromTheFlowBuilder(): void
    {
        $this->givenOrder();

        $this->action()->handleFlow($this->flow(self::ORDER_ID));

        $this->assertSame('Refund through Shopware Flow Builder', $this->refundBuilder->getLastCall()['description']);
    }

    /**
     * The flow has to see the failure, or a merchant would believe the money was refunded.
     */
    public function testAFailedRefundIsReportedBackToTheFlow(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->action()->handleFlow($this->flow('an-order-that-does-not-exist'));
    }

    private function givenOrder(): void
    {
        $builder = new OrderEntityBuilder();

        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setVersionId('version-1');
        $order->setOrderNumber('10000');
        $order->setSalesChannelId('sales-channel-1');
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
        $order->setAmountTotal(25.0);
        $order->setAmountNet(21.01);
        $order->setLineItems(new OrderLineItemCollection([$builder->createShippableLineItem('line-item-1', 'SW-1', 2, 10.0)]));
        $order->setDeliveries(new OrderDeliveryCollection([$builder->createShippableDelivery('delivery-1', 'line-item-1', 5.0)]));

        $state = new StateMachineStateEntity();
        $state->setId('paid-state');
        $state->setTechnicalName(OrderTransactionStates::STATE_PAID);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');
        $transaction->setStateMachineState($state);

        $payment = new Payment('tr_1');
        $payment->setShopwareTransaction($transaction);
        $transaction->addExtension(Mollie::EXTENSION, $payment);

        $order->setTransactions(new OrderTransactionCollection([$transaction]));
        $order->addExtension(OrderExtension::REFUND_PROPERTY_NAME, new RefundCollection());

        $this->orderRepository->add($order);

        $this->seedRefundRepository();
    }

    private function seedRefundRepository(): void
    {
        $this->refundRepository->entityWrittenContainerEvents[] = new EntityWrittenContainerEvent(
            $this->context,
            new NestedEventCollection(),
            []
        );

        $storedRefund = new RefundEntity();
        $storedRefund->setId('stored-refund-1');
        $storedRefund->setInternalDescription('');

        $this->refundRepository->entitySearchResults[] = new EntitySearchResult(
            RefundDefinition::ENTITY_NAME,
            1,
            new RefundCollection([$storedRefund]),
            null,
            new Criteria(),
            $this->context
        );
    }

    private function flow(string $orderId): StorableFlow
    {
        return new StorableFlow(RefundOrderAction::getName(), $this->context, ['orderId' => $orderId]);
    }

    private function action(): RefundOrderAction
    {
        return new RefundOrderAction($this->createRefundRoute(), new NullLogger());
    }

    private function createRefundRoute(): CreateRefundRoute
    {
        $logger = new NullLogger();

        $route = new CreateRefundRoute(
            new RefundOrderLoader(
                $this->orderRepository,
                new FakeGateway('', new Payment('tr_1')),
                new EventSpy()
            ),
            new RefundCompositionBuilder(),
            new RefundTotalsBuilder(new RefundableTotalCalculator(), $logger),
            $this->refundGateway,
            $this->refundBuilder,
            new RefundPersister($this->refundRepository, new FakeStockStorage(), new RefundItemSplitter()),
            new EventSpy(),
            new FakeSettingsService(),
            new CreditNoteService(
                new FakeEntityRepository(new OrderDefinition()),
                new FakeEntityRepository(new OrderLineItemDefinition()),
                new FakeRecalculationService(),
                $logger
            ),
            new FakeTransactionService(),
            $logger
        );

        // AbstractController::json() asks the container for a serializer; an empty container makes
        // it fall back to json_encode.
        $route->setContainer(new Container());

        return $route;
    }
}
