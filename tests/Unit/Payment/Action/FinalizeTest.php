<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Action;

use Mollie\Shopware\Component\FlowBuilder\Event\Payment\CancelledEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Payment\FailedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Payment\SuccessEvent;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Payment\Action\Finalize;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

final class FinalizeTest extends TestCase implements EventDispatcherInterface
{
    private object $event;
    private Context $context;

    public function setUp(): void
    {
        $this->context = new Context(new SystemSource());
    }

    public function testSuccessEventIsFired(): void
    {
        $fakePayment = $this->getPayment();
        $fakePayment->setStatus(PaymentStatus::PAID);

        $paymentFinalize = $this->getFinalizeAction($fakePayment);
        $paymentFinalize->execute(new PaymentTransactionStruct('test'), $this->context);

        $this->assertInstanceOf(SuccessEvent::class, $this->event);
    }

    public function testCancelEventIsFired(): void
    {
        $fakePayment = $this->getPayment();
        $fakePayment->setStatus(PaymentStatus::CANCELED);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/customer canceled the external payment process/');

        $paymentFinalize = $this->getFinalizeAction($fakePayment);
        $paymentFinalize->execute(new PaymentTransactionStruct('test'), $this->context);
        $this->assertInstanceOf(CancelledEvent::class, $this->event);
    }

    public function testFailedEventIsFired(): void
    {
        $fakePayment = $this->getPayment();
        $fakePayment->setStatus(PaymentStatus::FAILED);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessageMatches('/payment finalize was interrupted/');

        $paymentFinalize = $this->getFinalizeAction($fakePayment);
        $paymentFinalize->execute(new PaymentTransactionStruct('test'), $this->context);
        $this->assertInstanceOf(FailedEvent::class, $this->event);
    }

    public function dispatch(object $event): void
    {
        $this->event = $event;
    }

    private function getFinalizeAction(Payment $fakePayment): Finalize
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();

        return new Finalize(
            $transactionService,
            new FakeGateway(payment: $fakePayment),
            $this,
            new NullLogger()
        );
    }

    private function getPayment(): Payment
    {
        $fakePayment = new Payment('test');
        $fakePayment->setMethod(PaymentMethod::CREDIT_CARD);

        return $fakePayment;
    }
}
