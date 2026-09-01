<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Handler;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Handler\AbstractMolliePaymentHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeFinalize;
use Mollie\Shopware\Unit\Payment\Fake\FakePay;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(AbstractMolliePaymentHandler::class)]
final class AbstractMolliePaymentHandlerTest extends TestCase
{
    private const TRANSACTION_ID = 'order-transaction-id';

    public function testPayRedirectsToTheCheckoutUrlOfThePayAction(): void
    {
        $pay = new FakePay();
        $handler = $this->makeHandler(pay: $pay);

        $response = $handler->pay(new Request(), $this->makeTransaction(), $this->makeContext(), null);

        $this->assertSame('https://mollie.com/checkout', $response->getTargetUrl());
        $this->assertSame(1, $pay->getCallCount());
    }

    public function testPayHandsTheOrderTransactionIdAndReturnUrlToThePayAction(): void
    {
        $pay = new FakePay();
        $handler = $this->makeHandler(pay: $pay);

        $handler->pay(new Request(), $this->makeTransaction(), $this->makeContext(), null);

        $this->assertSame(self::TRANSACTION_ID, $pay->getLastTransaction()->getOrderTransactionId());
        $this->assertSame('https://shop.example/return', $pay->getLastTransaction()->getReturnUrl());
    }

    public function testTransactionWithoutReturnUrlBecomesAnEmptyReturnUrl(): void
    {
        $pay = new FakePay();
        $handler = $this->makeHandler(pay: $pay);

        $handler->pay(new Request(), new PaymentTransactionStruct(self::TRANSACTION_ID), $this->makeContext(), null);

        $this->assertSame('', $pay->getLastTransaction()->getReturnUrl());
    }

    public function testAFailingPayActionAbortsTheAsyncProcess(): void
    {
        $handler = $this->makeHandler(pay: new FakePay(new \RuntimeException('mollie is down')));

        $this->expectException(PaymentException::class);
        $handler->pay(new Request(), $this->makeTransaction(), $this->makeContext(), null);
    }

    public function testFinalizeDelegatesToTheFinalizeAction(): void
    {
        $finalize = new FakeFinalize();
        $handler = $this->makeHandler(finalize: $finalize);

        $handler->finalize(new Request(), $this->makeTransaction(), $this->makeContext());

        $this->assertSame(1, $finalize->getCallCount());
        $this->assertSame(self::TRANSACTION_ID, $finalize->getLastTransaction()->getOrderTransactionId());
    }

    public function testFinalizeKeepsAShopwarePaymentExceptionAsItIs(): void
    {
        $customerCanceled = PaymentException::customerCanceled(self::TRANSACTION_ID, 'customer canceled');
        $handler = $this->makeHandler(finalize: new FakeFinalize($customerCanceled));

        try {
            $handler->finalize(new Request(), $this->makeTransaction(), $this->makeContext());
            $this->fail('finalize did not throw');
        } catch (PaymentException $exception) {
            $this->assertSame($customerCanceled, $exception);
        }
    }

    public function testAnUnexpectedFinalizeFailureAbortsTheAsyncProcess(): void
    {
        $handler = $this->makeHandler(finalize: new FakeFinalize(new \RuntimeException('mollie is down')));

        $this->expectException(PaymentException::class);
        $handler->finalize(new Request(), $this->makeTransaction(), $this->makeContext());
    }

    public function testNoPaymentHandlerTypeIsSupported(): void
    {
        $handler = $this->makeHandler();

        $this->assertFalse($handler->supports(PaymentHandlerType::REFUND, 'payment-method-id', $this->makeContext()));
        $this->assertFalse($handler->supports(PaymentHandlerType::RECURRING, 'payment-method-id', $this->makeContext()));
    }

    private function makeHandler(?FakePay $pay = null, ?FakeFinalize $finalize = null): FakePaymentMethodHandler
    {
        return new FakePaymentMethodHandler(
            PaymentMethod::PAYPAL,
            'Fake payment method',
            $pay ?? new FakePay(),
            $finalize ?? new FakeFinalize(),
            new NullLogger()
        );
    }

    private function makeTransaction(): PaymentTransactionStruct
    {
        return new PaymentTransactionStruct(self::TRANSACTION_ID, 'https://shop.example/return');
    }

    private function makeContext(): Context
    {
        return new Context(new SystemSource());
    }
}
