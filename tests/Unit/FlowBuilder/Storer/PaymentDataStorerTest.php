<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FlowBuilder\Storer;

use Mollie\Shopware\Component\FlowBuilder\Event\MolliePaymentAware;
use Mollie\Shopware\Component\FlowBuilder\Event\Payment\SuccessEvent;
use Mollie\Shopware\Component\FlowBuilder\Storer\PaymentDataStorer;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\FakeFlowEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;

/**
 * A flow only ever sees what a storer put into the flow storage, so the Mollie payment has to
 * survive the way from the event into the stored data and back out again.
 */
#[CoversClass(PaymentDataStorer::class)]
final class PaymentDataStorerTest extends TestCase
{
    public function testStoreKeepsThePaymentOfTheEvent(): void
    {
        $payment = new Payment('tr_stored');
        $storer = new PaymentDataStorer();

        $stored = $storer->store($this->paymentEvent($payment), []);

        $this->assertSame($payment, $stored[MolliePaymentAware::PAYMENT_STORAGE_KEY]);
    }

    public function testStoreIgnoresAnEventWithoutAMolliePayment(): void
    {
        $storer = new PaymentDataStorer();

        $stored = $storer->store(new FakeFlowEvent(), []);

        $this->assertSame([], $stored);
    }

    public function testStoreDoesNotOverwriteAPaymentThatIsAlreadyStored(): void
    {
        $alreadyStored = new Payment('tr_first');
        $storer = new PaymentDataStorer();

        $stored = $storer->store(
            $this->paymentEvent(new Payment('tr_second')),
            [MolliePaymentAware::PAYMENT_STORAGE_KEY => $alreadyStored]
        );

        $this->assertSame($alreadyStored, $stored[MolliePaymentAware::PAYMENT_STORAGE_KEY]);
    }

    public function testRestoreMakesTheStoredPaymentReadableForTheFlow(): void
    {
        $payment = new Payment('tr_restored');
        $flow = new StorableFlow(
            'mollie.payment.success',
            Context::createDefaultContext(),
            [MolliePaymentAware::PAYMENT_STORAGE_KEY => $payment],
            []
        );

        (new PaymentDataStorer())->restore($flow);

        $this->assertSame($payment, $flow->getData(MolliePaymentAware::PAYMENT_STORAGE_KEY));
    }

    public function testRestoreAddsNoDataWhenNoPaymentWasStored(): void
    {
        $flow = new StorableFlow('mollie.payment.success', Context::createDefaultContext(), [], []);

        (new PaymentDataStorer())->restore($flow);

        $this->assertNull($flow->getData(MolliePaymentAware::PAYMENT_STORAGE_KEY));
    }

    private function paymentEvent(Payment $payment): SuccessEvent
    {
        $order = new OrderEntity();
        $order->setId('order-id');

        return new SuccessEvent($payment, $order, CustomerBuilder::create()->build(), Context::createDefaultContext());
    }
}
