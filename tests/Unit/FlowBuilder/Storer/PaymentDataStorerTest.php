<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Storer;

use Mollie\Shopware\Component\FlowBuilder\Event\MolliePaymentAware;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPaidEvent;
use Mollie\Shopware\Component\FlowBuilder\Storer\PaymentDataStorer;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Unit\Fake\FakeFlowEventAware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

#[CoversClass(PaymentDataStorer::class)]
final class PaymentDataStorerTest extends TestCase
{
    public function testStoreAddsPaymentForMolliePaymentAwareEvent(): void
    {
        $payment = new Payment('tr_test');
        $order = new OrderEntity();
        $order->setId('order-1');
        $context = new Context(new SystemSource());

        $event = new WebhookStatusPaidEvent($payment, $order, $context);

        $storer = new PaymentDataStorer();
        $result = $storer->store($event, []);

        $this->assertArrayHasKey(MolliePaymentAware::PAYMENT_STORAGE_KEY, $result);
        $this->assertSame($payment, $result[MolliePaymentAware::PAYMENT_STORAGE_KEY]);
    }

    public function testStoreDoesNotOverwriteExistingKey(): void
    {
        $payment = new Payment('tr_test');
        $order = new OrderEntity();
        $order->setId('order-1');
        $context = new Context(new SystemSource());

        $event = new WebhookStatusPaidEvent($payment, $order, $context);

        $existingPayment = new Payment('tr_existing');
        $stored = [MolliePaymentAware::PAYMENT_STORAGE_KEY => $existingPayment];

        $storer = new PaymentDataStorer();
        $result = $storer->store($event, $stored);

        $this->assertSame($existingPayment, $result[MolliePaymentAware::PAYMENT_STORAGE_KEY]);
    }

    public function testStoreIgnoresNonMolliePaymentAwareEvent(): void
    {
        $event = new FakeFlowEventAware();

        $storer = new PaymentDataStorer();
        $result = $storer->store($event, ['other_key' => 'value']);

        $this->assertArrayNotHasKey(MolliePaymentAware::PAYMENT_STORAGE_KEY, $result);
        $this->assertSame('value', $result['other_key']);
    }

    public function testRestoreMovesPaymentFromStoredToData(): void
    {
        $payment = new Payment('tr_test');
        $context = new Context(new SystemSource());

        $storable = new StorableFlow(
            'test.event',
            $context,
            [MolliePaymentAware::PAYMENT_STORAGE_KEY => $payment]
        );

        $storer = new PaymentDataStorer();
        $storer->restore($storable);

        $this->assertSame($payment, $storable->getData(MolliePaymentAware::PAYMENT_STORAGE_KEY));
    }

    public function testRestoreDoesNothingWhenKeyNotInStore(): void
    {
        $context = new Context(new SystemSource());
        $storable = new StorableFlow('test.event', $context, []);

        $storer = new PaymentDataStorer();
        $storer->restore($storable);

        $this->assertNull($storable->getData(MolliePaymentAware::PAYMENT_STORAGE_KEY));
    }
}
