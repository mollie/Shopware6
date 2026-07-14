<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscriber;

use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Subscriber\ZugferdInvoiceGeneratedSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdInvoiceGeneratedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(ZugferdInvoiceGeneratedSubscriber::class)]
final class ZugferdInvoiceGeneratedSubscriberTest extends TestCase
{
    public function testMolliePaymentAddsPaymentMeansBlock(): void
    {
        $builder = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3);
        $document = new ZugferdDocument($builder);
        $order = $this->buildOrder(PaymentMethod::CREDIT_CARD);

        $subscriber = new ZugferdInvoiceGeneratedSubscriber();
        $subscriber->onInvoiceGenerated($this->buildEvent($document, $order));

        $xml = $builder->getContent();
        $this->assertStringContainsString('SpecifiedTradeSettlementPaymentMeans', $xml);
        $this->assertStringContainsString('<ram:TypeCode>48</ram:TypeCode>', $xml);
    }

    public function testFallbackMethodUsesOnlinePaymentServiceCode(): void
    {
        $builder = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3);
        $document = new ZugferdDocument($builder);
        $order = $this->buildOrder(PaymentMethod::IDEAL);

        $subscriber = new ZugferdInvoiceGeneratedSubscriber();
        $subscriber->onInvoiceGenerated($this->buildEvent($document, $order));

        $xml = $builder->getContent();
        $this->assertStringContainsString('<ram:TypeCode>68</ram:TypeCode>', $xml);
    }

    public function testNonMollieTransactionLeavesDocumentUntouched(): void
    {
        $builder = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3);
        $document = new ZugferdDocument($builder);

        $transaction = new OrderTransactionEntity();
        $transaction->setId(Uuid::randomHex());
        $order = new OrderEntity();
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        $subscriber = new ZugferdInvoiceGeneratedSubscriber();
        $subscriber->onInvoiceGenerated($this->buildEvent($document, $order));

        $xml = $builder->getContent();
        $this->assertStringNotContainsString('SpecifiedTradeSettlementPaymentMeans', $xml);
    }

    private function buildOrder(PaymentMethod $method): OrderEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $paymentMethod->setName('Mollie ' . $method->value);

        $payment = new Payment('tr_' . $method->value);
        $payment->setMethod($method);

        $transaction = new OrderTransactionEntity();
        $transaction->setId(Uuid::randomHex());
        $transaction->setPaymentMethod($paymentMethod);
        $transaction->addExtension(Mollie::EXTENSION, $payment);

        $order = new OrderEntity();
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        return $order;
    }

    private function buildEvent(ZugferdDocument $document, OrderEntity $order): ZugferdInvoiceGeneratedEvent
    {
        return new ZugferdInvoiceGeneratedEvent(
            $document,
            $order,
            new DocumentConfiguration(),
            Context::createDefaultContext()
        );
    }
}
