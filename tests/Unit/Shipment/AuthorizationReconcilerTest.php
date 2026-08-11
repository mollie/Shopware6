<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Shipment;

use Mollie\Shopware\Component\Mollie\LineItemFilter;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\ShippingItem;
use Mollie\Shopware\Component\Mollie\ShippingItemCollection;
use Mollie\Shopware\Component\Shipment\AuthorizationReconciler;
use Mollie\Shopware\Component\Shipment\ShipmentItemResolver;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
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

    private function createReconciler(FakeGateway $gateway): AuthorizationReconciler
    {
        $lineItemFilter = new LineItemFilter();
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
