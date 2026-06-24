<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Event;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Event\ModifyCreatePaymentPayloadEvent;
use Mollie\Shopware\Component\Payment\Event\PaymentCreatedEvent;
use Mollie\Shopware\Component\Payment\Event\PaymentFinalizeEvent;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

#[CoversClass(PaymentCreatedEvent::class)]
#[CoversClass(PaymentFinalizeEvent::class)]
#[CoversClass(ModifyCreatePaymentPayloadEvent::class)]
final class PaymentEventsTest extends TestCase
{
    public function testPaymentFinalizeEventStoresPaymentAndContext(): void
    {
        $payment = new Payment('pay-1');
        $context = Context::createDefaultContext();

        $event = new PaymentFinalizeEvent($payment, $context);

        $this->assertSame($payment, $event->getPayment());
        $this->assertSame($context, $event->getContext());
    }

    public function testModifyCreatePaymentPayloadEventStoresPaymentAndContext(): void
    {
        $createPayment = new CreatePayment('Order #1', 'https://example.com/return', new Money(10.00, 'EUR'));
        $context = Context::createDefaultContext();

        $event = new ModifyCreatePaymentPayloadEvent($createPayment, $context);

        $this->assertSame($createPayment, $event->getPayment());
        $this->assertSame($context, $event->getContext());
    }

    public function testPaymentCreatedEventStoresAllValues(): void
    {
        $redirectUrl = 'https://mollie.com/checkout/abc';
        $payment = new Payment('pay-2');
        $dataBag = new RequestDataBag(['key' => 'value']);
        $context = Context::createDefaultContext();

        $fakeTransactionService = new FakeTransactionService();
        $fakeTransactionService->createTransaction();
        $transactionDataStruct = $fakeTransactionService->findById('tx-1', $context);

        $event = new PaymentCreatedEvent($redirectUrl, $payment, $transactionDataStruct, $dataBag, $context);

        $this->assertSame($redirectUrl, $event->getRedirectUrl());
        $this->assertSame($payment, $event->getPayment());
        $this->assertSame($transactionDataStruct, $event->getTransactionDataStruct());
        $this->assertSame($dataBag, $event->getRequestDataBag());
        $this->assertSame($context, $event->getContext());
    }
}
