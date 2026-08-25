<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Shipment;

use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\ShippingItem;
use Mollie\Shopware\Component\Mollie\ShippingItemCollection;
use Mollie\Shopware\Component\Shipment\AuthorizationReconciler;
use Mollie\Shopware\Component\Shipment\ShipmentItemResolver;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Builder\LineItemFilterBuilder;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

#[CoversClass(AuthorizationReconciler::class)]
final class AuthorizationReconcilerTest extends TestCase
{
    public function testCaptureViaPaymentsApiCapturesAndReturnsCaptureId(): void
    {
        $gateway = new FakeGateway();
        $reconciler = $this->createReconciler($gateway);

        $shippingItems = new ShippingItemCollection();
        $shippingItems->add(new ShippingItem(1, 10.0, null));

        $mollieId = $reconciler->captureViaPaymentsApi(
            new Payment('tr_1'),
            $shippingItems,
            $this->orderWithoutRoundingDiff(),
            $this->cleanLineItems(),
            $this->currency(),
            'SW10001',
            'sales-channel',
            false,
            [],
        );

        self::assertNotNull($mollieId);
        self::assertCount(1, $gateway->getCapturePayloads());
        self::assertSame(10.0, $gateway->getCapturePayloads()[0]->getAmount()->getValue());
    }

    public function testCaptureDescriptionContainsTheOrderNumber(): void
    {
        // The capture description shows up in the Mollie balances report, so it must identify the
        // order instead of listing the shipped items.
        $gateway = new FakeGateway();
        $reconciler = $this->createReconciler($gateway);

        $shippingItems = new ShippingItemCollection();
        $shippingItems->add(new ShippingItem(1, 10.0, null));

        $reconciler->captureViaPaymentsApi(
            new Payment('tr_1'),
            $shippingItems,
            $this->orderWithoutRoundingDiff(),
            $this->cleanLineItems(),
            $this->currency(),
            'SW10001',
            'sales-channel',
            false,
            [],
        );

        self::assertCount(1, $gateway->getCapturePayloads());
        self::assertSame('SW10001', $gateway->getCapturePayloads()[0]->getDescription());
    }

    public function testFullShipmentCapturesAuthorizedRemainderRegardlessOfLineItemTotal(): void
    {
        // The shipped line items sum to only 91.92 because the rounding-difference line is not a
        // Shopware line item. On a full shipment the capture must still land on the full authorized
        // 91.94 (amount - amountCaptured), independent of the rounding-line recognition.
        $payment = new Payment('tr_1');
        $payment->setAmount(new Money(91.94, 'EUR'));
        $payment->setCapturedAmount(new Money(0.0, 'EUR'));

        $gateway = new FakeGateway('', $payment);
        $reconciler = $this->createReconciler($gateway);

        $shippingItems = new ShippingItemCollection();
        $shippingItems->add(new ShippingItem(1, 91.92, null));

        $mollieId = $reconciler->captureViaPaymentsApi(
            $payment,
            $shippingItems,
            $this->orderWithoutRoundingDiff(),
            $this->cleanLineItems(),
            $this->currency(),
            'SW10001',
            'sales-channel',
            true,
            [],
        );

        self::assertNotNull($mollieId);
        self::assertCount(1, $gateway->getCapturePayloads());
        self::assertSame(91.94, $gateway->getCapturePayloads()[0]->getAmount()->getValue());
    }

    public function testFullShipmentTopsUpOnlyTheUncapturedRemainder(): void
    {
        // A prior shipment already captured 50.00 of the authorized 90.00; the final full shipment
        // must top up exactly the remaining 40.00.
        $payment = new Payment('tr_1');
        $payment->setAmount(new Money(90.0, 'EUR'));
        $payment->setCapturedAmount(new Money(50.0, 'EUR'));

        $gateway = new FakeGateway('', $payment);
        $reconciler = $this->createReconciler($gateway);

        $shippingItems = new ShippingItemCollection();
        $shippingItems->add(new ShippingItem(1, 39.98, null));

        $mollieId = $reconciler->captureViaPaymentsApi(
            $payment,
            $shippingItems,
            $this->orderWithoutRoundingDiff(),
            $this->cleanLineItems(),
            $this->currency(),
            'SW10001',
            'sales-channel',
            true,
            [],
        );

        self::assertNotNull($mollieId);
        self::assertCount(1, $gateway->getCapturePayloads());
        self::assertSame(40.0, $gateway->getCapturePayloads()[0]->getAmount()->getValue());
    }

    public function testCaptureViaPaymentsApiReturnsNullWhenMollieCallFails(): void
    {
        $gateway = new FakeGateway();
        $gateway->withCaptureThrowing();
        $reconciler = $this->createReconciler($gateway);

        $shippingItems = new ShippingItemCollection();
        $shippingItems->add(new ShippingItem(1, 10.0, null));

        $mollieId = $reconciler->captureViaPaymentsApi(
            new Payment('tr_1'),
            $shippingItems,
            $this->orderWithoutRoundingDiff(),
            $this->cleanLineItems(),
            $this->currency(),
            'SW10001',
            'sales-channel',
            false,
            [],
        );

        self::assertNull($mollieId);
    }

    public function testReconcileReturnsEmptyResponseForOrdersApiOrders(): void
    {
        $gateway = new FakeGateway();
        $reconciler = $this->createReconciler($gateway);

        // Orders API is line-item based; there is no single amount to reconcile, so this is a no-op.
        $response = $reconciler->reconcileAuthorizedRemainder(
            $this->orderWithoutRoundingDiff(),
            new Payment('tr_1'),
            $this->currency(),
            '',
            'SW10001',
            'sales-channel',
            'ord_mollie_1',
            new OrderDeliveryCollection(),
            new OrderLineItemCollection(),
            [],
        );

        self::assertSame('', $response->getMollieId());
        self::assertCount(0, $gateway->getCapturePayloads());
    }

    public function testFullyHandledOrderWithCancellationsReleasesTheRemainingAuthorization(): void
    {
        // The shipped items are captured, everything else the customer authorized (cancelled items and
        // the rounding difference) is released so it is never charged.
        $gateway = new FakeGateway();
        $reconciler = $this->createReconciler($gateway);

        $shippingItems = new ShippingItemCollection();
        $shippingItems->add(new ShippingItem(1, 10.0, null));

        $reconciler->captureViaPaymentsApi(
            new Payment('tr_1'),
            $shippingItems,
            $this->orderWithoutRoundingDiff(),
            $this->cancelledLineItems(),
            $this->currency(),
            'SW10001',
            'sales-channel',
            true,
            [],
        );

        self::assertSame([[
            'paymentId' => 'tr_1',
            'orderNumber' => 'SW10001',
            'salesChannelId' => 'sales-channel',
        ]], $gateway->getReleasedAuthorizations());
    }

    public function testFailedReleaseDoesNotUndoTheSuccessfulCapture(): void
    {
        $gateway = new FakeGateway();
        $gateway->withReleaseAuthorizationThrowing();
        $reconciler = $this->createReconciler($gateway);

        $shippingItems = new ShippingItemCollection();
        $shippingItems->add(new ShippingItem(1, 10.0, null));

        $mollieId = $reconciler->captureViaPaymentsApi(
            new Payment('tr_1'),
            $shippingItems,
            $this->orderWithoutRoundingDiff(),
            $this->cancelledLineItems(),
            $this->currency(),
            'SW10001',
            'sales-channel',
            true,
            [],
        );

        self::assertNotNull($mollieId);
        self::assertCount(1, $gateway->getCapturePayloads());
    }

    public function testFullShipmentFallsBackToTheShippedTotalWhenTheMolliePaymentCannotBeLoaded(): void
    {
        $gateway = new FakeGateway();
        $gateway->withGetPaymentThrowing();
        $reconciler = $this->createReconciler($gateway);

        $shippingItems = new ShippingItemCollection();
        $shippingItems->add(new ShippingItem(1, 10.0, null));

        $reconciler->captureViaPaymentsApi(
            new Payment('tr_1'),
            $shippingItems,
            $this->orderWithoutRoundingDiff(),
            $this->cleanLineItems(),
            $this->currency(),
            'SW10001',
            'sales-channel',
            true,
            [],
        );

        self::assertCount(1, $gateway->getCapturePayloads());
        self::assertSame(10.0, $gateway->getCapturePayloads()[0]->getAmount()->getValue());
    }

    public function testFirstPartialShipmentAddsTheRoundingDifferenceFromTheMolliePayment(): void
    {
        // Orders created before the rounding difference was persisted on the order only carry it on the
        // Mollie payment, so the first partial shipment has to read it from there.
        $payment = new Payment('tr_1');
        $payment->setRoundingDiff(0.02);

        $gateway = new FakeGateway('', $payment);
        $reconciler = $this->createReconciler($gateway);

        $shippingItems = new ShippingItemCollection();
        $shippingItems->add(new ShippingItem(1, 10.0, null));

        $reconciler->captureViaPaymentsApi(
            $payment,
            $shippingItems,
            $this->orderWithoutMollieCustomFields(),
            $this->cleanLineItems(),
            $this->currency(),
            'SW10001',
            'sales-channel',
            false,
            [],
        );

        self::assertCount(1, $gateway->getCapturePayloads());
        self::assertSame(10.02, $gateway->getCapturePayloads()[0]->getAmount()->getValue());
    }

    public function testFirstPartialShipmentCapturesTheShippedTotalWhenTheRoundingDifferenceIsUnknown(): void
    {
        $gateway = new FakeGateway();
        $gateway->withGetPaymentThrowing();
        $reconciler = $this->createReconciler($gateway);

        $shippingItems = new ShippingItemCollection();
        $shippingItems->add(new ShippingItem(1, 10.0, null));

        $reconciler->captureViaPaymentsApi(
            new Payment('tr_1'),
            $shippingItems,
            $this->orderWithoutMollieCustomFields(),
            $this->cleanLineItems(),
            $this->currency(),
            'SW10001',
            'sales-channel',
            false,
            [],
        );

        self::assertCount(1, $gateway->getCapturePayloads());
        self::assertSame(10.0, $gateway->getCapturePayloads()[0]->getAmount()->getValue());
    }

    public function testReconcileReturnsEmptyResponseWhenTheMolliePaymentCannotBeLoaded(): void
    {
        $gateway = new FakeGateway();
        $gateway->withGetPaymentThrowing();
        $reconciler = $this->createReconciler($gateway);

        $response = $reconciler->reconcileAuthorizedRemainder(
            $this->orderWithoutRoundingDiff(),
            new Payment('tr_1'),
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
            'SW10001',
            'sales-channel',
            null,
            new OrderDeliveryCollection(),
            new OrderLineItemCollection(),
            [],
        );

        self::assertSame('', $response->getMollieId());
        self::assertCount(0, $gateway->getCapturePayloads());
    }

    public function testReconcileTopsUpTheAmountThatIsStillAuthorized(): void
    {
        // Older orders were captured with the net amount: 90.00 authorized, 50.00 captured and nothing
        // left to ship in Shopware means the missing 40.00 has to be captured now.
        $freshPayment = new Payment('tr_1');
        $freshPayment->setAmount(new Money(90.0, 'EUR'));
        $freshPayment->setCapturedAmount(new Money(50.0, 'EUR'));
        $freshPayment->setAmountRemaining(new Money(40.0, 'EUR'));

        $gateway = new FakeGateway('', $freshPayment);
        $reconciler = $this->createReconciler($gateway);

        $response = $reconciler->reconcileAuthorizedRemainder(
            $this->orderWithoutRoundingDiff(),
            $freshPayment,
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
            'SW10001',
            'sales-channel',
            null,
            new OrderDeliveryCollection(),
            $this->cleanLineItems(),
            [],
        );

        self::assertNotSame('', $response->getMollieId());
        self::assertCount(1, $gateway->getCapturePayloads());
        self::assertSame(40.0, $gateway->getCapturePayloads()[0]->getAmount()->getValue());
        self::assertSame('shipping-SW10001', $gateway->getCapturePayloads()[0]->getDescription());
    }

    public function testReconcileReturnsEmptyResponseWhenTheTopUpCaptureFails(): void
    {
        $freshPayment = new Payment('tr_1');
        $freshPayment->setAmount(new Money(90.0, 'EUR'));
        $freshPayment->setCapturedAmount(new Money(50.0, 'EUR'));
        $freshPayment->setAmountRemaining(new Money(40.0, 'EUR'));

        $gateway = new FakeGateway('', $freshPayment);
        $gateway->withCaptureThrowing();
        $reconciler = $this->createReconciler($gateway);

        $response = $reconciler->reconcileAuthorizedRemainder(
            $this->orderWithoutRoundingDiff(),
            $freshPayment,
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
            'SW10001',
            'sales-channel',
            null,
            new OrderDeliveryCollection(),
            $this->cleanLineItems(),
            [],
        );

        self::assertSame('', $response->getMollieId());
        self::assertCount(0, $gateway->getReleasedAuthorizations());
    }

    public function testReconcileReleasesTheAuthorizationOfCancelledItems(): void
    {
        // 90.00 authorized, 10.00 shipped and already captured, the rest belongs to cancelled items and
        // is released instead of captured.
        $freshPayment = new Payment('tr_1');
        $freshPayment->setAmount(new Money(90.0, 'EUR'));
        $freshPayment->setCapturedAmount(new Money(10.0, 'EUR'));
        $freshPayment->setAmountRemaining(new Money(80.0, 'EUR'));

        $gateway = new FakeGateway('', $freshPayment);
        $reconciler = $this->createReconciler($gateway);

        $response = $reconciler->reconcileAuthorizedRemainder(
            $this->orderWithoutRoundingDiff(),
            $freshPayment,
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
            'SW10001',
            'sales-channel',
            null,
            new OrderDeliveryCollection(),
            $this->cancelledLineItems(),
            [],
        );

        self::assertSame('tr_1', $response->getMollieId());
        self::assertCount(0, $gateway->getCapturePayloads());
        self::assertCount(1, $gateway->getReleasedAuthorizations());
    }

    public function testReconcileReportsNothingWhenTheReleaseFails(): void
    {
        $freshPayment = new Payment('tr_1');
        $freshPayment->setAmount(new Money(90.0, 'EUR'));
        $freshPayment->setCapturedAmount(new Money(10.0, 'EUR'));
        $freshPayment->setAmountRemaining(new Money(80.0, 'EUR'));

        $gateway = new FakeGateway('', $freshPayment);
        $gateway->withReleaseAuthorizationThrowing();
        $reconciler = $this->createReconciler($gateway);

        $response = $reconciler->reconcileAuthorizedRemainder(
            $this->orderWithoutRoundingDiff(),
            $freshPayment,
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
            'SW10001',
            'sales-channel',
            null,
            new OrderDeliveryCollection(),
            $this->cancelledLineItems(),
            [],
        );

        self::assertSame('', $response->getMollieId());
    }

    public function testReconcileReportsNothingWhenTheAuthorizedAmountIsAlreadyCaptured(): void
    {
        $freshPayment = new Payment('tr_1');
        $freshPayment->setAmount(new Money(90.0, 'EUR'));
        $freshPayment->setCapturedAmount(new Money(90.0, 'EUR'));
        $freshPayment->setAmountRemaining(new Money(40.0, 'EUR'));

        $gateway = new FakeGateway('', $freshPayment);
        $reconciler = $this->createReconciler($gateway);

        $response = $reconciler->reconcileAuthorizedRemainder(
            $this->orderWithoutRoundingDiff(),
            $freshPayment,
            $this->currency(),
            CartPrice::TAX_STATE_GROSS,
            'SW10001',
            'sales-channel',
            null,
            new OrderDeliveryCollection(),
            $this->cleanLineItems(),
            [],
        );

        self::assertSame('', $response->getMollieId());
        self::assertCount(0, $gateway->getCapturePayloads());
        self::assertCount(0, $gateway->getReleasedAuthorizations());
    }

    private function createReconciler(FakeGateway $gateway): AuthorizationReconciler
    {
        $lineItemFilter = LineItemFilterBuilder::build();
        $itemResolver = new ShipmentItemResolver($lineItemFilter);
        $logger = new NullLogger();

        return new AuthorizationReconciler($gateway, $itemResolver, $logger);
    }

    private function currency(): CurrencyEntity
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        return $currency;
    }

    private function cleanLineItems(): OrderLineItemCollection
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId('li1');

        return new OrderLineItemCollection([$lineItem]);
    }

    /**
     * One item of two shipped, the other cancelled - the shipped gross is 10.00.
     */
    private function cancelledLineItems(): OrderLineItemCollection
    {
        $builder = new OrderEntityBuilder();

        return new OrderLineItemCollection([
            $builder->createShippableLineItem('li1', 'SW100', 2, 10.0, ['quantity' => 1, 'cancelled_quantity' => 1]),
        ]);
    }

    private function orderWithoutMollieCustomFields(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order-1');

        return $order;
    }

    private function orderWithoutRoundingDiff(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order-1');
        // Provide the rounding difference explicitly so the capture path does not fall back to the
        // Mollie API to resolve it.
        $order->setCustomFields([Mollie::EXTENSION => ['rounding_diff' => 0.0]]);

        return $order;
    }
}
