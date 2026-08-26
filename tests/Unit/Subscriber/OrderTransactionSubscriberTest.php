<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscriber;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Subscriber\OrderTransactionSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;

#[CoversClass(OrderTransactionSubscriber::class)]
final class OrderTransactionSubscriberTest extends TestCase
{
    public function testPaymentDetailsAreHydratedFromCustomFields(): void
    {
        $transaction = $this->buildTransaction([
            'id' => 'tr_xxx',
            'method' => 'paypal',
            'paypalPayerId' => 'PAYER-1',
            'creditCardLabel' => 'VISA',
            'creditCardNumber' => '1234',
            'creditCardHolder' => 'John Doe',
            'bankAccount' => 'NL12345',
            'bankName' => 'Test Bank',
        ]);

        $subscriber = new OrderTransactionSubscriber();
        $subscriber->onOrderTransaction($this->buildEvent([$transaction]));

        $payment = $transaction->getExtension(Mollie::EXTENSION);
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame(['payerId' => 'PAYER-1'], $payment->getPaypalDetails());
        $this->assertSame(
            ['label' => 'VISA', 'number' => '1234', 'holder' => 'John Doe'],
            $payment->getCreditCardDetails()
        );
        $this->assertNotNull($payment->getBankTransferDetails());
    }

    public function testNoExtensionIsAddedWithoutPaymentId(): void
    {
        $transaction = $this->buildTransaction(['method' => 'paypal']);

        $subscriber = new OrderTransactionSubscriber();
        $subscriber->onOrderTransaction($this->buildEvent([$transaction]));

        $this->assertFalse($transaction->hasExtension(Mollie::EXTENSION));
    }

    public function testEveryLoadedTransactionIsHydrated(): void
    {
        $this->assertArrayHasKey(
            OrderEvents::ORDER_TRANSACTION_LOADED_EVENT,
            OrderTransactionSubscriber::getSubscribedEvents()
        );
    }

    /**
     * A pay-by-link transaction has no payment id until the customer pays, so the extension has to
     * be attached from the link id alone - otherwise the admin loses the link.
     */
    public function testAPayByLinkTransactionIsHydratedFromTheLinkIdAlone(): void
    {
        $transaction = $this->buildTransaction(['paymentLinkId' => 'pl_1']);

        (new OrderTransactionSubscriber())->onOrderTransaction($this->buildEvent([$transaction]));

        $payment = $transaction->getExtension(Mollie::EXTENSION);
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame('pl_1', $payment->getPaymentLinkId());
        $this->assertSame('', $payment->getId());
    }

    public function testTheCheckoutUrlsAreHydrated(): void
    {
        $transaction = $this->buildTransaction([
            'id' => 'tr_1',
            'checkoutUrl' => 'https://mollie.test/checkout',
            'changePaymentStateUrl' => 'https://mollie.test/change-method',
            'finalizeUrl' => 'https://shop.test/finalize',
        ]);

        (new OrderTransactionSubscriber())->onOrderTransaction($this->buildEvent([$transaction]));

        $payment = $transaction->getExtension(Mollie::EXTENSION);
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame('https://mollie.test/checkout', $payment->getCheckoutUrl());
        $this->assertSame('https://mollie.test/change-method', $payment->getChangePaymentStateUrl());
        $this->assertSame('https://shop.test/finalize', $payment->getFinalizeUrl());
    }

    public function testTheOrdersApiIdsAreHydrated(): void
    {
        $transaction = $this->buildTransaction([
            'id' => 'tr_1',
            'orderId' => 'ord_1',
            'thirdPartyPaymentId' => 'PAYPAL-1',
            'authenticationId' => 'auth_1',
        ]);

        (new OrderTransactionSubscriber())->onOrderTransaction($this->buildEvent([$transaction]));

        $payment = $transaction->getExtension(Mollie::EXTENSION);
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame('ord_1', $payment->getOrderId());
        $this->assertSame('PAYPAL-1', $payment->getThirdPartyPaymentId());
        $this->assertSame('auth_1', $payment->getAuthenticationId());
    }

    /**
     * Voucher and rounding amounts are stored as strings in the custom fields, but the refund cap
     * calculates with them - they have to come back as numbers.
     */
    public function testVoucherAndRoundingAmountsAreHydratedAsNumbers(): void
    {
        $transaction = $this->buildTransaction([
            'id' => 'tr_1',
            'voucherAmount' => '5.50',
            'roundingDiff' => '0.01',
        ]);

        (new OrderTransactionSubscriber())->onOrderTransaction($this->buildEvent([$transaction]));

        $payment = $transaction->getExtension(Mollie::EXTENSION);
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame(5.5, $payment->getVoucherAmount());
        $this->assertSame(0.01, $payment->getRoundingDiff());
    }

    /**
     * The ids an accounting export reads must survive the round trip, or saving the payment again
     * would drop the earlier refunds and captures.
     */
    public function testTheStoredRefundCaptureAndShipmentIdsAreKept(): void
    {
        $transaction = $this->buildTransaction([
            'id' => 'tr_1',
            'refundIds' => 're_1, re_2',
            'captureIds' => 'cpt_1',
            'shipmentIds' => 'shp_1,shp_2',
        ]);

        (new OrderTransactionSubscriber())->onOrderTransaction($this->buildEvent([$transaction]));

        $payment = $transaction->getExtension(Mollie::EXTENSION);
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame(['re_1', 're_2'], $payment->getRefundIds());
        $this->assertSame(['cpt_1'], $payment->getCaptureIds());
        $this->assertSame(['shp_1', 'shp_2'], $payment->getShipmentIds());
    }

    public function testIdsStoredInAnOlderFormatAreDroppedInsteadOfBreakingTheOrder(): void
    {
        $transaction = $this->buildTransaction(['id' => 'tr_1', 'refundIds' => ['re_1', 're_2']]);

        (new OrderTransactionSubscriber())->onOrderTransaction($this->buildEvent([$transaction]));

        $payment = $transaction->getExtension(Mollie::EXTENSION);
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame([], $payment->getRefundIds());
    }

    public function testAnExtensionThatIsAlreadyThereIsNotReplaced(): void
    {
        $transaction = $this->buildTransaction(['id' => 'tr_from_custom_fields']);
        $alreadyLoaded = new Payment('tr_already_loaded');
        $transaction->addExtension(Mollie::EXTENSION, $alreadyLoaded);

        (new OrderTransactionSubscriber())->onOrderTransaction($this->buildEvent([$transaction]));

        $this->assertSame($alreadyLoaded, $transaction->getExtension(Mollie::EXTENSION));
    }

    public function testATransactionWithoutCustomFieldsIsLeftAlone(): void
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');

        (new OrderTransactionSubscriber())->onOrderTransaction($this->buildEvent([$transaction]));

        $this->assertFalse($transaction->hasExtension(Mollie::EXTENSION));
    }

    /**
     * The loaded event is fired for every entity type, so anything that is not a transaction has
     * to be passed over instead of hydrated.
     */
    public function testAnEntityThatIsNotATransactionIsPassedOver(): void
    {
        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setCustomFields([Mollie::EXTENSION => ['id' => 'tr_1']]);

        (new OrderTransactionSubscriber())->onOrderTransaction($this->buildEvent([$order]));

        $this->assertFalse($order->hasExtension(Mollie::EXTENSION));
    }

    /**
     * @param array<string, mixed> $mollieCustomFields
     */
    private function buildTransaction(array $mollieCustomFields): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');
        $transaction->setCustomFields([Mollie::EXTENSION => $mollieCustomFields]);

        return $transaction;
    }

    /**
     * @param list<\Shopware\Core\Framework\DataAbstractionLayer\Entity> $transactions
     *
     * @return EntityLoadedEvent<OrderTransactionEntity>
     */
    private function buildEvent(array $transactions): EntityLoadedEvent
    {
        return new EntityLoadedEvent(
            new OrderTransactionDefinition(),
            $transactions,
            Context::createDefaultContext()
        );
    }
}
