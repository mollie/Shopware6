<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Shipment;

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
        $reconciler = new AuthorizationReconciler($gateway, new ShipmentItemResolver(), new NullLogger());

        $shippingItems = new ShippingItemCollection();
        $shippingItems->add(new ShippingItem(1, '1x Product', 10.0, null));

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

    public function testCaptureViaPaymentsApiReturnsNullWhenMollieCallFails(): void
    {
        $gateway = new FakeGateway();
        $gateway->withCaptureThrowing();
        $reconciler = new AuthorizationReconciler($gateway, new ShipmentItemResolver(), new NullLogger());

        $shippingItems = new ShippingItemCollection();
        $shippingItems->add(new ShippingItem(1, '1x Product', 10.0, null));

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
        $reconciler = new AuthorizationReconciler($gateway, new ShipmentItemResolver(), new NullLogger());

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
