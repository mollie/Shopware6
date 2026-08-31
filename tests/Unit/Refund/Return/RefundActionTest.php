<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Return;

use Mollie\Shopware\Component\Refund\Return\AbstractReturnAction;
use Mollie\Shopware\Component\Refund\Return\RefundAction;
use Mollie\Shopware\Component\Refund\Return\Struct\OrderReturnLineItemStruct;
use Mollie\Shopware\Component\Refund\Return\Struct\OrderReturnStruct;
use Mollie\Shopware\Component\Settings\Struct\RefundSettings;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use Mollie\Shopware\Unit\Refund\Fake\FakeCreateRefundRoute;
use Mollie\Shopware\Unit\Refund\Return\Fake\FakeOrderReturnLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;

/**
 * What a return of the Return Management turns into when it is refunded. The composition matters as
 * much as the amount: Mollie recalculates the refund from the order lines when the items are
 * missing, and ignores what the return asked for.
 */
#[CoversClass(RefundAction::class)]
#[CoversClass(AbstractReturnAction::class)]
final class RefundActionTest extends TestCase
{
    private const RETURN_ID = 'return-1';
    private const ORDER_ID = 'order-1';
    private const LINE_ITEM_ID = 'line-item-1';
    private const DELIVERY_ID = 'delivery-1';

    public function testNothingIsRefundedWithoutSwagCommercial(): void
    {
        $route = new FakeCreateRefundRoute();
        $action = $this->action($route, new FakeOrderReturnLoader(available: false));

        $action->execute(self::RETURN_ID, Context::createDefaultContext());

        $this->assertSame([], $route->calls);
    }

    public function testNothingIsRefundedWhenTheReturnCannotBeFound(): void
    {
        $route = new FakeCreateRefundRoute();
        $action = $this->action($route, new FakeOrderReturnLoader(orderReturn: null));

        $action->execute(self::RETURN_ID, Context::createDefaultContext());

        $this->assertSame([], $route->calls);
    }

    public function testNothingIsRefundedWhenTheReturnCarriesNoOrder(): void
    {
        $route = new FakeCreateRefundRoute();
        $action = $this->action($route, new FakeOrderReturnLoader($this->orderReturn(withOrder: false)));

        $action->execute(self::RETURN_ID, Context::createDefaultContext());

        $this->assertSame([], $route->calls);
    }

    public function testNothingIsRefundedWhenTheMerchantSwitchedTheIntegrationOff(): void
    {
        $route = new FakeCreateRefundRoute();
        $action = $this->action(
            $route,
            new FakeOrderReturnLoader($this->orderReturn()),
            new RefundSettings(returnManagementDisabled: true)
        );

        $action->execute(self::RETURN_ID, Context::createDefaultContext());

        $this->assertSame([], $route->calls);
    }

    public function testTheRecalculatedTotalOfTheReturnIsRefunded(): void
    {
        $route = new FakeCreateRefundRoute();
        $action = $this->action($route, new FakeOrderReturnLoader($this->orderReturn(amountTotal: 12.5)));

        $action->execute(self::RETURN_ID, Context::createDefaultContext());

        $this->assertSame(12.5, $route->calls[0]['payload']['amount']);
    }

    /**
     * The total is only written once the return was recalculated. Without it the positions are all
     * there is to go by.
     */
    public function testTheAmountFallsBackToTheSumOfThePositions(): void
    {
        $route = new FakeCreateRefundRoute();
        $orderReturn = $this->orderReturn(amountTotal: null, lineItems: [
            new OrderReturnLineItemStruct(self::LINE_ITEM_ID, 2, 10.0, 2),
        ]);

        $this->action($route, new FakeOrderReturnLoader($orderReturn))
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertSame(10.0, $route->calls[0]['payload']['amount']);
    }

    public function testThePositionCarriesQuantityAmountAndRestock(): void
    {
        $route = new FakeCreateRefundRoute();
        $orderReturn = $this->orderReturn(lineItems: [
            new OrderReturnLineItemStruct(self::LINE_ITEM_ID, 2, 10.0, 1),
        ]);

        $this->action($route, new FakeOrderReturnLoader($orderReturn))
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $item = $route->calls[0]['payload']['items'][0];

        $this->assertSame(self::LINE_ITEM_ID, $item['id']);
        $this->assertSame(2, $item['quantity']);
        $this->assertSame(10.0, $item['amount']);
        $this->assertSame(1, $item['resetStock']);
    }

    public function testThePositionIsLabelledWithTheOrderLineItem(): void
    {
        $route = new FakeCreateRefundRoute();
        $orderReturn = $this->orderReturn(lineItems: [
            new OrderReturnLineItemStruct(self::LINE_ITEM_ID, 1, 10.0, 0),
        ]);

        $this->action($route, new FakeOrderReturnLoader($orderReturn))
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertSame('Product SW-1', $route->calls[0]['payload']['items'][0]['label']);
    }

    public function testAPositionOfAnUnknownOrderLineItemHasNoLabel(): void
    {
        $route = new FakeCreateRefundRoute();
        $orderReturn = $this->orderReturn(lineItems: [
            new OrderReturnLineItemStruct('not-in-the-order', 1, 10.0, 0),
        ]);

        $this->action($route, new FakeOrderReturnLoader($orderReturn))
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertSame('', $route->calls[0]['payload']['items'][0]['label']);
    }

    /**
     * A return may give the shipping costs back as well. They are a position of their own, keyed by
     * the delivery, so the refund manager can tell them apart from the goods.
     */
    public function testReturnedShippingCostsBecomeADeliveryPosition(): void
    {
        $route = new FakeCreateRefundRoute();
        $orderReturn = $this->orderReturn(shippingCostsTotal: 4.99, lineItems: [
            new OrderReturnLineItemStruct(self::LINE_ITEM_ID, 1, 10.0, 0),
        ]);

        $this->action($route, new FakeOrderReturnLoader($orderReturn))
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $items = $route->calls[0]['payload']['items'];

        $this->assertCount(2, $items);
        $this->assertSame(self::DELIVERY_ID, $items[1]['id']);
        $this->assertSame(4.99, $items[1]['amount']);
        $this->assertSame(1, $items[1]['quantity']);
        $this->assertSame(0, $items[1]['resetStock']);
    }

    public function testNoDeliveryPositionWithoutReturnedShippingCosts(): void
    {
        $route = new FakeCreateRefundRoute();
        $orderReturn = $this->orderReturn(shippingCostsTotal: 0.0, lineItems: [
            new OrderReturnLineItemStruct(self::LINE_ITEM_ID, 1, 10.0, 0),
        ]);

        $this->action($route, new FakeOrderReturnLoader($orderReturn))
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertCount(1, $route->calls[0]['payload']['items']);
    }

    /**
     * Mollie stores the returnId on the refund. It is what keeps a second transition from refunding
     * the same return twice.
     */
    public function testTheReturnIdIsSentWithTheRefund(): void
    {
        $route = new FakeCreateRefundRoute();

        $this->action($route, new FakeOrderReturnLoader($this->orderReturn()))
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertSame(self::RETURN_ID, $route->calls[0]['payload']['returnId']);
    }

    public function testTheInternalCommentOfTheReturnIsTheInternalDescription(): void
    {
        $route = new FakeCreateRefundRoute();

        $this->action($route, new FakeOrderReturnLoader($this->orderReturn(internalComment: 'broken on arrival')))
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertSame('broken on arrival', $route->calls[0]['payload']['internalDescription']);
        $this->assertSame('', $route->calls[0]['payload']['description']);
    }

    /**
     * The return is read in the working version the merchant edited, but the refund has to persist
     * against the live version: mollie_refund cascades on the order version and would be dropped
     * with it.
     */
    public function testTheReturnIsReadInTheWorkingVersionButRefundedOnTheLiveVersion(): void
    {
        $route = new FakeCreateRefundRoute();
        $loader = new FakeOrderReturnLoader($this->orderReturn());
        $workingVersion = '0198a1b2c3d4e5f60718293a4b5c6d7e';

        $this->action($route, $loader)
            ->execute(self::RETURN_ID, Context::createDefaultContext()->createWithVersionId($workingVersion))
        ;

        $this->assertSame($workingVersion, $loader->loadCalls[0]['versionId']);
        $this->assertSame(Defaults::LIVE_VERSION, $route->calls[0]['versionId']);
    }

    public function testAFailingRefundIsSwallowedSoTheTransitionStillCompletes(): void
    {
        $route = new FakeCreateRefundRoute(new \RuntimeException('Mollie is down'));

        $this->action($route, new FakeOrderReturnLoader($this->orderReturn()))
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertCount(1, $route->calls);
    }

    // ------------------------------------------------------------ created as done

    /**
     * A return that is inserted already carrying the done state never transitions, so no state
     * change event fires for it.
     */
    public function testAReturnCreatedAsDoneIsRefunded(): void
    {
        $route = new FakeCreateRefundRoute();

        $this->action($route, new FakeOrderReturnLoader($this->orderReturn(state: 'done')))
            ->executeOnCreate(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertCount(1, $route->calls);
    }

    public function testAReturnCreatedInAnyOtherStateIsNotRefunded(): void
    {
        $route = new FakeCreateRefundRoute();

        $this->action($route, new FakeOrderReturnLoader($this->orderReturn(state: 'open')))
            ->executeOnCreate(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertSame([], $route->calls);
    }

    public function testAReturnCreatedWithoutAStateIsNotRefunded(): void
    {
        $route = new FakeCreateRefundRoute();

        $this->action($route, new FakeOrderReturnLoader($this->orderReturn(state: null)))
            ->executeOnCreate(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertSame([], $route->calls);
    }

    // ----------------------------------------------------------------- helpers

    private function action(
        FakeCreateRefundRoute $route,
        FakeOrderReturnLoader $loader,
        ?RefundSettings $refundSettings = null,
    ): RefundAction {
        return new RefundAction(
            $route,
            $loader,
            new FakeSettingsService(refundSettings: $refundSettings),
            new NullLogger()
        );
    }

    /**
     * @param OrderReturnLineItemStruct[] $lineItems
     */
    private function orderReturn(
        ?string $state = 'done',
        ?float $amountTotal = 10.0,
        string $internalComment = '',
        float $shippingCostsTotal = 0.0,
        array $lineItems = [],
        bool $withOrder = true,
    ): OrderReturnStruct {
        return new OrderReturnStruct(
            self::RETURN_ID,
            $state,
            $withOrder ? $this->order() : null,
            $amountTotal,
            $internalComment,
            $shippingCostsTotal,
            $lineItems
        );
    }

    private function order(): OrderEntity
    {
        $builder = new OrderEntityBuilder();

        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setOrderNumber('10000');
        $order->setSalesChannelId('sales-channel-1');
        $order->setLineItems(new OrderLineItemCollection([
            $builder->createShippableLineItem(self::LINE_ITEM_ID, 'SW-1', 2, 10.0),
        ]));
        $order->setDeliveries(new OrderDeliveryCollection([
            $builder->createShippableDelivery(self::DELIVERY_ID, self::LINE_ITEM_ID, 4.99),
        ]));

        return $order;
    }
}
