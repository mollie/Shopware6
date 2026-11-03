<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Subscriber;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Event\PaymentFinalizeEvent;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionBuilder;
use Mollie\Shopware\Component\Payment\ExpressComponents\Subscriber\SessionPaymentCleanupSubscriber;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeOrderTransactionRepository;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeSessionPaymentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;

#[CoversClass(SessionPaymentCleanupSubscriber::class)]
final class SessionPaymentCleanupSubscriberTest extends TestCase
{
    private FakeOrderTransactionRepository $orderTransactionRepository;
    private FakeSessionPaymentRepository $sessionPaymentRepository;

    protected function setUp(): void
    {
        $this->orderTransactionRepository = new FakeOrderTransactionRepository();
        $this->sessionPaymentRepository = new FakeSessionPaymentRepository();
    }

    public function testCleanupRunsAfterTheDevWebhookSubscriber(): void
    {
        $subscribedEvents = SessionPaymentCleanupSubscriber::getSubscribedEvents();

        $this->assertLessThan(0, $subscribedEvents[PaymentFinalizeEvent::class][1]);
    }

    public function testRegularTransactionIsLeftUntouched(): void
    {
        $this->createSubscriber()->onFinalize($this->createEvent([]));

        $this->assertSame([], $this->orderTransactionRepository->getUpserts());
        $this->assertSame([], $this->sessionPaymentRepository->getDeletedTransactionIds());
    }

    public function testExpressMarkersAreRemovedFromTransactionAndOrder(): void
    {
        $this->createSubscriber()->onFinalize($this->createEvent([TransactionService::TRANSACTION_CUSTOM_FIELD_EXPRESS_CHECKOUT => true]));

        $upsert = $this->orderTransactionRepository->getUpserts()[0];
        $this->assertSame('transaction-1', $upsert['id']);
        $this->assertNull($upsert['customFields'][TransactionService::TRANSACTION_CUSTOM_FIELD_EXPRESS_CHECKOUT]);
        $this->assertSame('order-1', $upsert['order']['id']);
        $this->assertNull($upsert['order']['customFields'][SessionBuilder::ORDER_CUSTOM_FIELD]);
    }

    public function testSessionPaymentEntryOfTheTransactionIsDeleted(): void
    {
        $this->createSubscriber()->onFinalize($this->createEvent([TransactionService::TRANSACTION_CUSTOM_FIELD_EXPRESS_CHECKOUT => true]));

        $this->assertSame(['transaction-1'], $this->sessionPaymentRepository->getDeletedTransactionIds());
    }

    private function createSubscriber(): SessionPaymentCleanupSubscriber
    {
        return new SessionPaymentCleanupSubscriber($this->orderTransactionRepository, $this->sessionPaymentRepository, new FakeLogger());
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function createEvent(array $customFields): PaymentFinalizeEvent
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');
        $transaction->setOrderId('order-1');
        $transaction->setCustomFields($customFields);

        $payment = new Payment('tr_1');
        $payment->setShopwareTransaction($transaction);

        return new PaymentFinalizeEvent($payment, Context::createDefaultContext());
    }
}
