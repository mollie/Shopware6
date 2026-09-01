<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Route;

use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\Refund as MollieRefund;
use Mollie\Shopware\Component\Mollie\RefundStatus;
use Mollie\Shopware\Component\Refund\CreditNoteService;
use Mollie\Shopware\Component\Refund\DAL\Order\OrderExtension;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundCollection;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundDefinition;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Mollie\Shopware\Component\Refund\DAL\RefundItem\RefundItemCollection;
use Mollie\Shopware\Component\Refund\DAL\RefundItem\RefundItemEntity;
use Mollie\Shopware\Component\Refund\RefundableTotalCalculator;
use Mollie\Shopware\Component\Refund\RefundCompositionBuilder;
use Mollie\Shopware\Component\Refund\RefundItemSplitter;
use Mollie\Shopware\Component\Refund\RefundOrderLoader;
use Mollie\Shopware\Component\Refund\RefundPersister;
use Mollie\Shopware\Component\Refund\RefundTotalsBuilder;
use Mollie\Shopware\Component\Refund\Route\CancelRefundRoute;
use Mollie\Shopware\Component\Refund\Route\CreateRefundRoute;
use Mollie\Shopware\Component\Refund\Route\RefundOverviewRoute;
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
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Container;

/**
 * The order in every route test is worth 25.00 gross: one line item of 2 x 10.00 plus 5.00
 * shipping. That is the refundable total the routes work from - not amountTotal, which credit
 * notes shrink on every refund.
 *
 * Amounts read back from the response are cast: JsonResponse encodes without
 * JSON_PRESERVE_ZERO_FRACTION, so a whole amount arrives as an int.
 *
 * The three refund routes share every collaborator, so the fakes and the order they are built
 * around live here instead of three times over.
 */
abstract class RefundRouteTestCase extends TestCase
{
    protected const ORDER_ID = 'order-1';
    protected const LINE_ITEM_ID = 'line-item-1';
    protected const DELIVERY_ID = 'delivery-1';
    protected const REFUNDABLE_TOTAL = 25.0;

    protected Context $context;

    protected FakeOrderSearchRepository $orderRepository;

    protected FakeRefundGateway $refundGateway;

    protected FakeRefundBuilder $refundBuilder;

    protected EventSpy $eventDispatcher;

    protected FakeTransactionService $transactionService;

    protected FakeEntityRepository $refundRepository;

    protected FakeEntityRepository $creditNoteOrderRepository;

    protected FakeEntityRepository $creditNoteLineItemRepository;

    protected FakeRecalculationService $recalculationService;

    protected ?Payment $freshPaymentForGateway = null;

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

    /**
     * @param list<MollieRefund> $mollieRefunds the refunds the Mollie API reports for the payment
     * @param list<RefundEntity> $storedRefunds the refund rows the plugin wrote for the order
     */
    protected function givenOrder(
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

    protected function molliePayment(): Payment
    {
        return new Payment('tr_1');
    }

    /**
     * @param list<MollieRefund> $refunds
     */
    protected function freshPayment(array $refunds = []): Payment
    {
        $payment = new Payment('tr_1');
        foreach ($refunds as $refund) {
            $payment->getRefunds()->add($refund);
        }

        return $payment;
    }

    protected function mollieRefund(string $id, float $amount, RefundStatus $status): MollieRefund
    {
        return new MollieRefund($id, 'tr_1', $status, new Money($amount, 'EUR'), '', new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    }

    /**
     * @param list<RefundItemEntity> $items
     */
    protected function storedRefund(string $mollieRefundId, array $items, string $internalDescription = ''): RefundEntity
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

    protected function refundItemForLineItem(int $quantity, float $amount): RefundItemEntity
    {
        $item = $this->refundItem($quantity, $amount);
        $item->setOrderLineItemId(self::LINE_ITEM_ID);

        return $item;
    }

    protected function refundItemForDelivery(int $quantity, float $amount): RefundItemEntity
    {
        $item = $this->refundItem($quantity, $amount);
        $item->setOrderDeliveryId(self::DELIVERY_ID);

        return $item;
    }

    protected function refundItem(int $quantity, float $amount): RefundItemEntity
    {
        $item = new RefundItemEntity();
        $item->setId(md5(sprintf('%d-%s', $quantity, $amount)));
        $item->setMollieLineId('odl_1');
        $item->setLabel('Product SW-1');
        $item->setQuantity($quantity);
        $item->setAmount($amount);

        return $item;
    }

    // ------------------------------------------------------------------ routes

    protected function overviewRoute(): RefundOverviewRoute
    {
        $route = new RefundOverviewRoute(
            $this->orderLoader(),
            new RefundCompositionBuilder(),
            $this->totalsBuilder(),
            new NullLogger()
        );

        return $this->withContainer($route);
    }

    protected function createRoute(?RefundSettings $refundSettings = null): CreateRefundRoute
    {
        $route = new CreateRefundRoute(
            $this->orderLoader(),
            new RefundCompositionBuilder(),
            $this->totalsBuilder(),
            $this->refundGateway,
            $this->refundBuilder,
            new RefundPersister($this->refundRepository, new FakeStockStorage(), new RefundItemSplitter()),
            $this->eventDispatcher,
            new FakeSettingsService(refundSettings: $refundSettings),
            $this->creditNoteService(),
            $this->transactionService,
            new NullLogger()
        );

        return $this->withContainer($route);
    }

    protected function cancelRoute(): CancelRefundRoute
    {
        $route = new CancelRefundRoute(
            $this->orderLoader(),
            new RefundCompositionBuilder(),
            $this->totalsBuilder(),
            $this->refundGateway,
            $this->creditNoteService(),
            $this->transactionService,
            new NullLogger()
        );

        return $this->withContainer($route);
    }

    /**
     * The gateway answers with a separate payment object, exactly as the Mollie API does.
     */
    protected function orderLoader(): RefundOrderLoader
    {
        return new RefundOrderLoader(
            $this->orderRepository,
            new FakeGateway('', $this->freshPaymentForGateway ?? $this->freshPayment()),
            $this->eventDispatcher
        );
    }

    protected function totalsBuilder(): RefundTotalsBuilder
    {
        return new RefundTotalsBuilder(new RefundableTotalCalculator(), new NullLogger());
    }

    protected function creditNoteService(): CreditNoteService
    {
        return new CreditNoteService(
            $this->creditNoteOrderRepository,
            $this->creditNoteLineItemRepository,
            $this->recalculationService,
            new NullLogger()
        );
    }

    /**
     * The persister writes the refund row and reads it back, so both the write event and the
     * reloaded entity have to be queued on the repository.
     */
    protected function seedRefundRepository(): void
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
    protected function storedRefundRow(): array
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
    protected function firstEventOfType(string $eventClass): object
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
    protected function cartItem(array $body, string $shopwareId): array
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
    protected function decode(string|false $content): array
    {
        return json_decode((string) $content, true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * AbstractController::json() asks the container for a serializer; an empty container makes it
     * fall back to json_encode, which is what the admin receives in production anyway.
     *
     * @template T of AbstractController
     *
     * @param T $route
     *
     * @return T
     */
    private function withContainer(AbstractController $route): AbstractController
    {
        $route->setContainer(new Container());

        return $route;
    }
}
