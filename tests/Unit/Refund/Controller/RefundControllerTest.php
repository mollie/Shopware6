<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Controller;

use Mollie\Shopware\Component\FlowBuilder\Event\Refund\RefundStartedEvent;
use Mollie\Shopware\Component\Mollie\CreatePaymentRefund;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\Refund as MollieRefund;
use Mollie\Shopware\Component\Mollie\RefundStatus;
use Mollie\Shopware\Component\Refund\Controller\RefundController;
use Mollie\Shopware\Component\Refund\CreditNoteService;
use Mollie\Shopware\Component\Refund\DAL\Order\OrderExtension;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundCollection;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundDefinition;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Mollie\Shopware\Component\Refund\DAL\RefundItem\RefundItemCollection;
use Mollie\Shopware\Component\Refund\DAL\RefundItem\RefundItemEntity;
use Mollie\Shopware\Component\Refund\Event\ModifyCreateRefundPayloadEvent;
use Mollie\Shopware\Component\Refund\RefundableTotalCalculator;
use Mollie\Shopware\Component\Refund\RefundItemSplitter;
use Mollie\Shopware\Component\Refund\RefundPersister;
use Mollie\Shopware\Component\Settings\Struct\RefundSettings;
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
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * The order in every test is worth 25.00 gross: one line item of 2 x 10.00 plus 5.00 shipping.
 * That is the refundable total the controller works from - not amountTotal, which credit notes
 * shrink on every refund.
 *
 * Amounts read back from the response are cast: JsonResponse encodes without
 * JSON_PRESERVE_ZERO_FRACTION, so a whole amount arrives as an int.
 */
#[CoversClass(RefundController::class)]
final class RefundControllerTest extends TestCase
{
    private const ORDER_ID = 'order-1';
    private const LINE_ITEM_ID = 'line-item-1';
    private const DELIVERY_ID = 'delivery-1';
    private const REFUNDABLE_TOTAL = 25.0;

    private Context $context;

    private FakeOrderSearchRepository $orderRepository;

    private FakeRefundGateway $refundGateway;

    private FakeRefundBuilder $refundBuilder;

    private EventSpy $eventDispatcher;

    private FakeTransactionService $transactionService;

    private FakeEntityRepository $refundRepository;

    private FakeEntityRepository $creditNoteOrderRepository;

    private FakeEntityRepository $creditNoteLineItemRepository;

    private FakeRecalculationService $recalculationService;

    private ?Payment $freshPaymentForGateway = null;

    protected function setUp(): void
    {
        $this->context = new Context(new SystemSource());
        $this->orderRepository = new FakeOrderSearchRepository();
        $this->refundGateway = new FakeRefundGateway();
        $this->refundBuilder = new FakeRefundBuilder();
        $this->eventDispatcher = new EventSpy();
        $this->transactionService = new FakeTransactionService();
        $this->refundRepository = new FakeEntityRepository(new RefundDefinition());
        $this->creditNoteOrderRepository = new FakeEntityRepository(new OrderDefinition());
        $this->creditNoteLineItemRepository = new FakeEntityRepository(new OrderLineItemDefinition());
        $this->recalculationService = new FakeRecalculationService();
    }

    // ---------------------------------------------------------------- overview

    public function testOverviewIsEmptyWithoutAMolliePaymentOnTheTransaction(): void
    {
        $this->givenOrder(molliePayment: null);

        $body = $this->overview();

        $this->assertSame([], $body['cart']);
        $this->assertSame(0.0, (float) $body['totals']['remaining']);
    }

    public function testOverviewRemainingIsTheRefundableTotalWithoutAnyRefund(): void
    {
        $this->givenOrder();

        $body = $this->overview();

        $this->assertSame(self::REFUNDABLE_TOTAL, (float) $body['totals']['remaining']);
        $this->assertSame(0.0, (float) $body['totals']['refunded']);
    }

    public function testOverviewRemainingSubtractsRefundedAndPendingAmounts(): void
    {
        $this->givenOrder(mollieRefunds: [
            $this->mollieRefund('re_1', 4.0, RefundStatus::Refunded),
            $this->mollieRefund('re_2', 6.0, RefundStatus::Pending),
        ]);

        $body = $this->overview();

        $this->assertSame(4.0, (float) $body['totals']['refunded']);
        $this->assertSame(6.0, (float) $body['totals']['pendingRefunds']);
        $this->assertSame(15.0, (float) $body['totals']['remaining']);
    }

    /**
     * Mollie can only refund captured money. A manual capture method that captured less than the
     * order total is the real ceiling, so the amount Mollie still accepts wins over the order total.
     */
    public function testOverviewRemainingIsCappedAtTheAmountMollieStillAccepts(): void
    {
        $freshPayment = $this->freshPayment();
        $freshPayment->setAmountRemaining(new Money(8.0, 'EUR'));

        $this->givenOrder(freshPayment: $freshPayment);

        $body = $this->overview();

        $this->assertSame(8.0, (float) $body['totals']['remaining']);
    }

    /**
     * Orders API refunds are line item based - Mollie derives the amount itself, so its payment
     * ceiling must not cap what the refund manager offers.
     */
    public function testOverviewRemainingIsNotCappedForAnOrdersApiPayment(): void
    {
        $molliePayment = $this->molliePayment();
        $molliePayment->setOrderId('ord_1');

        $freshPayment = $this->freshPayment();
        $freshPayment->setAmountRemaining(new Money(8.0, 'EUR'));

        $this->givenOrder(molliePayment: $molliePayment, freshPayment: $freshPayment);

        $body = $this->overview();

        $this->assertSame(self::REFUNDABLE_TOTAL, (float) $body['totals']['remaining']);
    }

    public function testOverviewRemainingNeverDropsBelowZero(): void
    {
        $this->givenOrder(mollieRefunds: [$this->mollieRefund('re_1', 30.0, RefundStatus::Refunded)]);

        $body = $this->overview();

        $this->assertSame(0.0, (float) $body['totals']['remaining']);
    }

    public function testOverviewCarriesTheVoucherAndRoundingAmountsOfThePayment(): void
    {
        $molliePayment = $this->molliePayment();
        $molliePayment->setVoucherAmount(7.5);
        $molliePayment->setRoundingDiff(-0.02);

        $this->givenOrder(molliePayment: $molliePayment);

        $body = $this->overview();

        $this->assertSame(7.5, (float) $body['totals']['voucherAmount']);
        $this->assertSame(-0.02, (float) $body['totals']['roundingDiff']);
    }

    public function testOverviewMarksAFullyRefundedUnitOfALineItem(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 10.0, RefundStatus::Refunded)],
            storedRefunds: [$this->storedRefund('re_1', [$this->refundItemForLineItem(quantity: 1, amount: 10.0)])],
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(1, $item['refunded']);
        $this->assertSame(10.0, (float) $item['refundedAmount']);
    }

    /**
     * A partial amount of a single unit counts as one refunded unit, so the merchant cannot refund
     * that unit a second time.
     */
    public function testOverviewCountsAPartiallyRefundedUnitAsOneRefundedUnit(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 4.0, RefundStatus::Refunded)],
            storedRefunds: [$this->storedRefund('re_1', [$this->refundItemForLineItem(quantity: 0, amount: 4.0)])],
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(1, $item['refunded']);
    }

    public function testOverviewRefundedQuantityNeverExceedsTheLineItemQuantity(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 20.0, RefundStatus::Refunded)],
            storedRefunds: [$this->storedRefund('re_1', [$this->refundItemForLineItem(quantity: 0, amount: 40.0)])],
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(2, $item['refunded']);
    }

    public function testOverviewIgnoresACanceledRefundForTheRefundedAmountOfALineItem(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 10.0, RefundStatus::Canceled)],
            storedRefunds: [$this->storedRefund('re_1', [$this->refundItemForLineItem(quantity: 1, amount: 10.0)])],
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(0, $item['refunded']);
        $this->assertSame(0.0, (float) $item['refundedAmount']);
    }

    /**
     * A pending refund is money that can no longer be refunded again, so it counts against the line
     * item just like a settled one.
     */
    public function testOverviewCountsAPendingRefundAgainstTheLineItem(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 10.0, RefundStatus::Pending)],
            storedRefunds: [$this->storedRefund('re_1', [$this->refundItemForLineItem(quantity: 1, amount: 10.0)])],
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(1, $item['refunded']);
    }

    public function testOverviewAddsTheRefundedShippingCostsToTheDelivery(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 5.0, RefundStatus::Refunded)],
            storedRefunds: [$this->storedRefund('re_1', [$this->refundItemForDelivery(quantity: 1, amount: 5.0)])],
        );

        $item = $this->cartItem($this->overview(), self::DELIVERY_ID);

        $this->assertSame(5.0, (float) $item['refundedAmount']);
    }

    public function testOverviewSumsTheAmountsOfSeveralRefundsOnTheSameLineItem(): void
    {
        $this->givenOrder(
            mollieRefunds: [
                $this->mollieRefund('re_1', 10.0, RefundStatus::Refunded),
                $this->mollieRefund('re_2', 4.0, RefundStatus::Refunded),
            ],
            storedRefunds: [
                $this->storedRefund('re_1', [$this->refundItemForLineItem(quantity: 1, amount: 10.0)]),
                $this->storedRefund('re_2', [$this->refundItemForLineItem(quantity: 0, amount: 4.0)]),
            ],
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(14.0, (float) $item['refundedAmount']);
    }

    /**
     * The Mollie refund list is what the admin renders, but the composition and the internal note
     * only exist in our own table - so the overview has to merge them onto the Mollie refund.
     */
    public function testOverviewEnrichesTheMollieRefundWithItsStoredComposition(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 10.0, RefundStatus::Refunded)],
            storedRefunds: [$this->storedRefund(
                're_1',
                [$this->refundItemForLineItem(quantity: 1, amount: 10.0)],
                internalDescription: 'Damaged on arrival'
            )],
        );

        $body = $this->overview();

        $this->assertSame('Damaged on arrival', $body['refunds'][0]['internalDescription']);
        $this->assertCount(1, $body['refunds'][0]['metadata']['composition']);
    }

    /**
     * For a net order the refund may include the line tax, so the ceiling of a line item is its
     * total plus its tax. Measured against the net total alone, 11.90 of a 20.00 + 3.80 line would
     * already count as two refunded units instead of one.
     */
    public function testOverviewAddsTheLineTaxToTheCeilingOfANetOrder(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 11.9, RefundStatus::Refunded)],
            storedRefunds: [$this->storedRefund('re_1', [$this->refundItemForLineItem(quantity: 0, amount: 11.9)])],
            taxState: CartPrice::TAX_STATE_NET,
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(1, $item['refunded']);
    }

    public function testOverviewIgnoresAStoredRefundThatMollieDoesNotReport(): void
    {
        $this->givenOrder(
            mollieRefunds: [$this->mollieRefund('re_1', 10.0, RefundStatus::Refunded)],
            storedRefunds: [$this->storedRefund('re_gone', [$this->refundItemForLineItem(quantity: 2, amount: 10.0)])],
        );

        $item = $this->cartItem($this->overview(), self::LINE_ITEM_ID);

        $this->assertSame(0, $item['refunded']);
    }

    // ------------------------------------------------------------------ create

    public function testCreateWithoutAmountAndItemsAsksTheBuilderForAFullRefund(): void
    {
        $this->givenOrder();

        $this->create([]);

        $call = $this->refundBuilder->getLastCall();
        $this->assertNull($call['amount']);
        $this->assertSame([], $call['items']);
    }

    public function testCreateStoresAFullRefundWithItsType(): void
    {
        $this->givenOrder();

        $this->create([]);

        $this->assertSame('FULL', $this->storedRefundRow()['type']);
    }

    public function testCreateStoresACustomAmountRefundAsPartial(): void
    {
        $this->givenOrder();

        $this->create(['amount' => 5.0]);

        $this->assertSame('PARTIAL', $this->storedRefundRow()['type']);
    }

    public function testCreatePassesTheRequestedAmountToTheBuilder(): void
    {
        $this->givenOrder();

        $this->create(['amount' => '7.25']);

        $this->assertSame(7.25, $this->refundBuilder->getLastCall()['amount']);
    }

    /**
     * The admin posts a row for every cart position, most of them untouched. Only the rows the
     * merchant actually filled in may reach the builder, otherwise a full refund is built instead.
     */
    public function testCreateDropsRequestedItemsWithoutQuantityAndWithoutAmount(): void
    {
        $this->givenOrder();

        $this->create(['items' => [
            ['id' => self::LINE_ITEM_ID, 'quantity' => 1, 'amount' => 0.0, 'resetStock' => 0],
            ['id' => self::DELIVERY_ID, 'quantity' => 0, 'amount' => 0.0, 'resetStock' => 0],
        ]]);

        $items = $this->refundBuilder->getLastCall()['items'];
        $this->assertCount(1, $items);
        $this->assertSame(self::LINE_ITEM_ID, $items[0]['id']);
    }

    public function testCreateKeepsARequestedItemThatOnlyCarriesAnAmount(): void
    {
        $this->givenOrder();

        $this->create(['items' => [
            ['id' => self::LINE_ITEM_ID, 'quantity' => 0, 'amount' => 3.5, 'resetStock' => 0],
        ]]);

        $this->assertCount(1, $this->refundBuilder->getLastCall()['items']);
    }

    public function testCreateStoresTheReturnIdAsRefundMetadata(): void
    {
        $this->givenOrder();

        $this->create(['returnId' => 'return-42']);

        $created = $this->refundGateway->getCreatedRefunds();
        $this->assertSame(['swagReturnId' => 'return-42'], $created[0]->getMetadata());
    }

    public function testCreateSendsNoMetadataWithoutAReturnId(): void
    {
        $this->givenOrder();

        $this->create([]);

        $created = $this->refundGateway->getCreatedRefunds();
        $this->assertSame([], $created[0]->getMetadata());
    }

    /**
     * The payload event is an extension point: whatever a listener returns is what must reach
     * Mollie, not the payload the builder produced.
     */
    public function testCreateSendsThePayloadTheModifyEventCarries(): void
    {
        $this->givenOrder();

        $replacement = new CreatePaymentRefund('tr_1', new Money(3.0, 'EUR'), 'replaced');
        $this->eventDispatcher->addListener(
            ModifyCreateRefundPayloadEvent::class,
            function (ModifyCreateRefundPayloadEvent $event) use ($replacement): void {
                $event->setCreateRefund($replacement);
            }
        );

        $this->create([]);

        $created = $this->refundGateway->getCreatedRefunds();
        $this->assertSame('replaced', $created[0]->getDescription());
    }

    public function testCreateDispatchesTheRefundStartedEventWithTheAmountMollieConfirmed(): void
    {
        $this->givenOrder();
        $this->refundGateway->withRefund($this->mollieRefund('re_new', 12.5, RefundStatus::Pending));

        $this->create([]);

        $event = $this->firstEventOfType(RefundStartedEvent::class);
        $this->assertSame(12.5, $event->getAmount());
    }

    /**
     * Mollie answers with the id of the refund. It has to land in the payment extension, so an
     * accounting export finds it in the custom fields of the order.
     */
    public function testCreateRecordsTheMollieRefundIdOnThePaymentExtension(): void
    {
        $molliePayment = $this->molliePayment();
        $this->givenOrder(molliePayment: $molliePayment);
        $this->refundGateway->withRefund($this->mollieRefund('re_new', 5.0, RefundStatus::Pending));

        $this->create([]);

        $saved = $this->transactionService->getSavedPaymentExtensions();
        $this->assertSame(['re_new'], $saved[0]['payment']->getRefundIds());
        $this->assertSame('transaction-1', $saved[0]['transactionId']);
    }

    public function testCreateAddsACreditNoteWhenTheSettingIsEnabled(): void
    {
        $this->givenOrder();
        $this->refundGateway->withRefund($this->mollieRefund('re_new', 5.0, RefundStatus::Pending));

        $this->create([], new RefundSettings(createCreditNotes: true));

        $this->assertNotNull($this->recalculationService->capturedLineItem);
    }

    public function testCreateAddsNoCreditNoteWhenTheSettingIsDisabled(): void
    {
        $this->givenOrder();
        $this->refundGateway->withRefund($this->mollieRefund('re_new', 5.0, RefundStatus::Pending));

        $this->create([]);

        $this->assertNull($this->recalculationService->capturedLineItem);
    }

    public function testCreateAnswersWithTheRecalculatedTotals(): void
    {
        $freshPayment = $this->freshPayment([$this->mollieRefund('re_new', 5.0, RefundStatus::Refunded)]);
        $this->givenOrder(freshPayment: $freshPayment);
        $this->refundGateway->withRefund($this->mollieRefund('re_new', 5.0, RefundStatus::Refunded));

        $body = $this->create([]);

        $this->assertSame(5.0, (float) $body['totals']['refunded']);
        $this->assertSame(20.0, (float) $body['totals']['remaining']);
    }

    public function testCreateFailsWithoutAMolliePaymentOnTheTransaction(): void
    {
        $this->givenOrder(molliePayment: null);

        $this->expectExceptionMessage('No Mollie payment extension found for order "order-1"');

        $this->create([]);
    }

    public function testCreateFailsForAnUnknownOrder(): void
    {
        $this->expectExceptionMessage('Order "order-1" not found');

        $this->create([]);
    }

    // ------------------------------------------------------------------ cancel

    public function testCancelPassesThePaymentAndTheRefundToMollie(): void
    {
        $this->givenOrder();

        $this->cancel('re_1');

        $this->assertSame(
            [['paymentId' => 'tr_1', 'refundId' => 're_1']],
            $this->refundGateway->getCancelledRefunds()
        );
    }

    /**
     * The refund is gone at Mollie, so its id must not stay in the export data of the order.
     */
    public function testCancelRemovesTheRefundIdFromThePaymentExtension(): void
    {
        $molliePayment = $this->molliePayment();
        $molliePayment->setRefundIds(['re_1', 're_2']);

        $this->givenOrder(molliePayment: $molliePayment);

        $this->cancel('re_1');

        $saved = $this->transactionService->getSavedPaymentExtensions();
        $this->assertSame(['re_2'], $saved[0]['payment']->getRefundIds());
    }

    public function testCancelReportsSuccessWithTheRecalculatedTotals(): void
    {
        $this->givenOrder();

        $body = $this->cancel('re_1');

        $this->assertTrue($body['success']);
        $this->assertSame(self::REFUNDABLE_TOTAL, (float) $body['totals']['remaining']);
    }

    public function testCancelFailsWithoutAMolliePaymentOnTheTransaction(): void
    {
        $this->givenOrder(molliePayment: null);

        $this->expectExceptionMessage('No Mollie payment extension found for order "order-1"');

        $this->cancel('re_1');
    }

    // ----------------------------------------------------------------- helpers

    /**
     * @param list<MollieRefund> $mollieRefunds the refunds the Mollie API reports for the payment
     * @param list<RefundEntity> $storedRefunds the refund rows the plugin wrote for the order
     */
    private function givenOrder(
        ?Payment $molliePayment = new Payment('tr_1'),
        ?Payment $freshPayment = null,
        array $mollieRefunds = [],
        array $storedRefunds = [],
        string $taxState = CartPrice::TAX_STATE_GROSS,
    ): void {
        $builder = new OrderEntityBuilder();

        $lineItems = new OrderLineItemCollection([
            $builder->createShippableLineItem(self::LINE_ITEM_ID, 'SW-1', 2, 10.0),
        ]);

        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setVersionId('version-1');
        $order->setOrderNumber('10000');
        $order->setSalesChannelId(TestDefaults::SALES_CHANNEL);
        $order->setTaxStatus($taxState);
        $order->setAmountTotal(self::REFUNDABLE_TOTAL);
        $order->setAmountNet(21.01);
        $order->setLineItems($lineItems);
        $order->setDeliveries(new OrderDeliveryCollection([
            $builder->createShippableDelivery(self::DELIVERY_ID, self::LINE_ITEM_ID, 5.0),
        ]));

        $state = new StateMachineStateEntity();
        $state->setId('paid-state');
        $state->setTechnicalName(OrderTransactionStates::STATE_PAID);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');
        $transaction->setStateMachineState($state);

        if ($molliePayment !== null) {
            $molliePayment->setShopwareTransaction($transaction);
            $transaction->addExtension(Mollie::EXTENSION, $molliePayment);
        }

        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        $refundCollection = new RefundCollection();
        foreach ($storedRefunds as $storedRefund) {
            $refundCollection->add($storedRefund);
        }
        $order->addExtension(OrderExtension::REFUND_PROPERTY_NAME, $refundCollection);

        $this->orderRepository->add($order);

        // The gateway answers with a separate payment object, exactly as the Mollie API does: it
        // knows the refunds and the current amounts, the transaction extension does not.
        $this->freshPaymentForGateway = $freshPayment ?? $this->freshPayment($mollieRefunds);
    }

    private function molliePayment(): Payment
    {
        return new Payment('tr_1');
    }

    /**
     * @param list<MollieRefund> $refunds
     */
    private function freshPayment(array $refunds = []): Payment
    {
        $payment = new Payment('tr_1');
        foreach ($refunds as $refund) {
            $payment->getRefunds()->add($refund);
        }

        return $payment;
    }

    private function mollieRefund(string $id, float $amount, RefundStatus $status): MollieRefund
    {
        return new MollieRefund($id, 'tr_1', $status, new Money($amount, 'EUR'), '', new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    }

    /**
     * @param list<RefundItemEntity> $items
     */
    private function storedRefund(string $mollieRefundId, array $items, string $internalDescription = ''): RefundEntity
    {
        $refund = new RefundEntity();
        $refund->setId(md5($mollieRefundId));
        $refund->setOrderId(self::ORDER_ID);
        $refund->setMollieRefundId($mollieRefundId);
        $refund->setInternalDescription($internalDescription);

        $collection = new RefundItemCollection();
        foreach ($items as $item) {
            $collection->add($item);
        }
        $refund->setRefundItems($collection);

        return $refund;
    }

    private function refundItemForLineItem(int $quantity, float $amount): RefundItemEntity
    {
        $item = $this->refundItem($quantity, $amount);
        $item->setOrderLineItemId(self::LINE_ITEM_ID);

        return $item;
    }

    private function refundItemForDelivery(int $quantity, float $amount): RefundItemEntity
    {
        $item = $this->refundItem($quantity, $amount);
        $item->setOrderDeliveryId(self::DELIVERY_ID);

        return $item;
    }

    private function refundItem(int $quantity, float $amount): RefundItemEntity
    {
        $item = new RefundItemEntity();
        $item->setId(md5(sprintf('%d-%s', $quantity, $amount)));
        $item->setMollieLineId('odl_1');
        $item->setLabel('Product SW-1');
        $item->setQuantity($quantity);
        $item->setAmount($amount);

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function overview(): array
    {
        $response = $this->controller()->overview(new Request([], ['orderId' => self::ORDER_ID]), $this->context);

        return $this->decode($response->getContent());
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function create(array $payload, ?RefundSettings $refundSettings = null): array
    {
        $this->seedRefundRepository();

        $response = $this->controller($refundSettings)->create(
            new Request([], array_merge(['orderId' => self::ORDER_ID], $payload)),
            $this->context
        );

        return $this->decode($response->getContent());
    }

    /**
     * @return array<string, mixed>
     */
    private function cancel(string $refundId): array
    {
        // No credit note line item exists for the refund, so the cancel leaves the order alone.
        $this->creditNoteLineItemRepository->idSearchResults[] = new IdSearchResult(0, [], new Criteria(), $this->context);

        $response = $this->controller()->cancel(
            new Request([], ['orderId' => self::ORDER_ID, 'refundId' => $refundId]),
            $this->context
        );

        return $this->decode($response->getContent());
    }

    private function controller(?RefundSettings $refundSettings = null): RefundController
    {
        $gateway = new FakeGateway('', $this->freshPaymentForGateway ?? $this->freshPayment());

        $controller = new RefundController(
            $this->orderRepository,
            $this->refundGateway,
            $gateway,
            $this->refundBuilder,
            new RefundPersister($this->refundRepository, new FakeStockStorage(), new RefundItemSplitter()),
            new RefundableTotalCalculator(),
            $this->eventDispatcher,
            new FakeSettingsService(refundSettings: $refundSettings),
            new CreditNoteService(
                $this->creditNoteOrderRepository,
                $this->creditNoteLineItemRepository,
                $this->recalculationService,
                new NullLogger()
            ),
            $this->transactionService,
            new NullLogger()
        );

        // AbstractController::json() asks the container for a serializer; an empty container makes
        // it fall back to json_encode, which is what the admin receives in production anyway.
        $controller->setContainer(new Container());

        return $controller;
    }

    /**
     * The persister writes the refund row and reads it back, so both the write event and the
     * reloaded entity have to be queued on the repository.
     */
    private function seedRefundRepository(): void
    {
        $this->refundRepository->entityWrittenContainerEvents[] = new EntityWrittenContainerEvent(
            $this->context,
            new NestedEventCollection(),
            []
        );

        $entity = new RefundEntity();
        $entity->setId('stored-refund-1');
        $entity->setInternalDescription('');

        $collection = new RefundCollection([$entity]);
        $this->refundRepository->entitySearchResults[] = new EntitySearchResult(
            RefundDefinition::ENTITY_NAME,
            1,
            $collection,
            null,
            new Criteria(),
            $this->context
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function storedRefundRow(): array
    {
        return $this->refundRepository->data[0][0];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $eventClass
     *
     * @return T
     */
    private function firstEventOfType(string $eventClass): object
    {
        foreach ($this->eventDispatcher->getEvents() as $event) {
            if ($event instanceof $eventClass) {
                return $event;
            }
        }

        throw new \RuntimeException(sprintf('No %s was dispatched.', $eventClass));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function cartItem(array $body, string $shopwareId): array
    {
        foreach ($body['cart'] as $item) {
            if ($item['shopware']['id'] === $shopwareId) {
                return $item;
            }
        }

        throw new \RuntimeException(sprintf('No cart item for "%s".', $shopwareId));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string|false $content): array
    {
        return json_decode((string) $content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
