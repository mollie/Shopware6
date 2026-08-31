<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Return;

use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\Refund as MollieRefund;
use Mollie\Shopware\Component\Mollie\RefundCollection;
use Mollie\Shopware\Component\Mollie\RefundStatus;
use Mollie\Shopware\Component\Refund\Return\CancelAction;
use Mollie\Shopware\Component\Refund\Return\Struct\OrderReturnStruct;
use Mollie\Shopware\Component\Settings\Struct\RefundSettings;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakeRefundGateway;
use Mollie\Shopware\Unit\Refund\Fake\FakeCancelRefundRoute;
use Mollie\Shopware\Unit\Refund\Return\Fake\FakeOrderReturnLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * Withdrawing a return has to undo the refund it created. The plugin does not store which Mollie
 * refund belongs to which return, so the refund is found by the returnId Mollie kept in its
 * metadata.
 */
#[CoversClass(CancelAction::class)]
final class CancelActionTest extends TestCase
{
    private const RETURN_ID = 'return-1';
    private const ORDER_ID = 'order-1';
    private const REFUND_ID = 're_1';

    public function testNothingIsCancelledWithoutSwagCommercial(): void
    {
        $route = new FakeCancelRefundRoute();

        $this->action($route, new FakeOrderReturnLoader(available: false))
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertSame([], $route->calls);
    }

    public function testNothingIsCancelledWhenTheReturnCannotBeFound(): void
    {
        $route = new FakeCancelRefundRoute();

        $this->action($route, new FakeOrderReturnLoader(orderReturn: null))
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertSame([], $route->calls);
    }

    public function testNothingIsCancelledWhenTheReturnCarriesNoOrder(): void
    {
        $route = new FakeCancelRefundRoute();

        $this->action($route, new FakeOrderReturnLoader($this->orderReturn(withOrder: false)))
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertSame([], $route->calls);
    }

    public function testNothingIsCancelledWhenTheMerchantSwitchedTheIntegrationOff(): void
    {
        $route = new FakeCancelRefundRoute();

        $this->action(
            $route,
            new FakeOrderReturnLoader($this->orderReturn()),
            refundSettings: new RefundSettings(returnManagementDisabled: true)
        )->execute(self::RETURN_ID, Context::createDefaultContext());

        $this->assertSame([], $route->calls);
    }

    /**
     * Without a Mollie payment on the order there is nothing that could have been refunded.
     */
    public function testNothingIsCancelledWithoutAMolliePaymentOnTheOrder(): void
    {
        $route = new FakeCancelRefundRoute();

        $this->action($route, new FakeOrderReturnLoader($this->orderReturn(withMolliePayment: false)))
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertSame([], $route->calls);
    }

    /**
     * Mollie reports refunds of the payment that belong to other returns too. Cancelling one of
     * those would take money back the merchant never withdrew.
     */
    public function testNothingIsCancelledWhenNoMollieRefundCarriesTheReturnId(): void
    {
        $route = new FakeCancelRefundRoute();
        $gateway = new FakeRefundGateway();
        $gateway->withRefundList(new RefundCollection([$this->mollieRefund('re_other', 'another-return')]));

        $this->action($route, new FakeOrderReturnLoader($this->orderReturn()), $gateway)
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertSame([], $route->calls);
    }

    public function testTheMollieRefundOfTheReturnIsCancelled(): void
    {
        $route = new FakeCancelRefundRoute();
        $gateway = new FakeRefundGateway();
        $gateway->withRefundList(new RefundCollection([
            $this->mollieRefund('re_other', 'another-return'),
            $this->mollieRefund(self::REFUND_ID, self::RETURN_ID),
        ]));

        $this->action($route, new FakeOrderReturnLoader($this->orderReturn()), $gateway)
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertCount(1, $route->calls);
        $this->assertSame(self::ORDER_ID, $route->calls[0]['payload']['orderId']);
        $this->assertSame(self::REFUND_ID, $route->calls[0]['payload']['refundId']);
    }

    /**
     * Same reason as the refund itself: mollie_refund cascades on the order version, so the write
     * has to happen against the live version.
     */
    public function testTheCancellationRunsAgainstTheLiveVersion(): void
    {
        $route = new FakeCancelRefundRoute();
        $gateway = new FakeRefundGateway();
        $gateway->withRefundList(new RefundCollection([$this->mollieRefund(self::REFUND_ID, self::RETURN_ID)]));

        $this->action($route, new FakeOrderReturnLoader($this->orderReturn()), $gateway)
            ->execute(self::RETURN_ID, Context::createDefaultContext()->createWithVersionId('0198a1b2c3d4e5f60718293a4b5c6d7e'))
        ;

        $this->assertSame(Defaults::LIVE_VERSION, $route->calls[0]['versionId']);
    }

    public function testAFailingCancellationIsSwallowedSoTheTransitionStillCompletes(): void
    {
        $route = new FakeCancelRefundRoute(new \RuntimeException('Mollie is down'));
        $gateway = new FakeRefundGateway();
        $gateway->withRefundList(new RefundCollection([$this->mollieRefund(self::REFUND_ID, self::RETURN_ID)]));

        $this->action($route, new FakeOrderReturnLoader($this->orderReturn()), $gateway)
            ->execute(self::RETURN_ID, Context::createDefaultContext())
        ;

        $this->assertCount(1, $route->calls);
    }

    // ----------------------------------------------------------------- helpers

    private function action(
        FakeCancelRefundRoute $route,
        FakeOrderReturnLoader $loader,
        ?FakeRefundGateway $refundGateway = null,
        ?RefundSettings $refundSettings = null,
    ): CancelAction {
        return new CancelAction(
            $route,
            $refundGateway ?? new FakeRefundGateway(),
            $loader,
            new FakeSettingsService(refundSettings: $refundSettings),
            new NullLogger()
        );
    }

    private function mollieRefund(string $id, string $returnId): MollieRefund
    {
        return new MollieRefund(
            $id,
            'tr_1',
            RefundStatus::Pending,
            new Money(10.0, 'EUR'),
            '',
            new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            ['swagReturnId' => $returnId]
        );
    }

    private function orderReturn(bool $withOrder = true, bool $withMolliePayment = true): OrderReturnStruct
    {
        return new OrderReturnStruct(
            self::RETURN_ID,
            'cancelled',
            $withOrder ? $this->order($withMolliePayment) : null,
            10.0,
            '',
            0.0,
            []
        );
    }

    private function order(bool $withMolliePayment): OrderEntity
    {
        $state = new StateMachineStateEntity();
        $state->setId('paid-state');
        $state->setTechnicalName(OrderTransactionStates::STATE_PAID);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');
        $transaction->setStateMachineState($state);

        if ($withMolliePayment) {
            $transaction->addExtension(Mollie::EXTENSION, new Payment('tr_1'));
        }

        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setOrderNumber('10000');
        $order->setSalesChannelId('sales-channel-1');
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        return $order;
    }
}
