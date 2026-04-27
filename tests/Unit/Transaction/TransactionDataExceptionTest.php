<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Transaction;

use Mollie\Shopware\Component\Transaction\TransactionDataException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(TransactionDataException::class)]
final class TransactionDataExceptionTest extends TestCase
{
    public function testTransactionNotFound(): void
    {
        $exception = TransactionDataException::transactionNotFound('tr_abc123');

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
        $this->assertSame(TransactionDataException::TRANSACTION_NOT_FOUND, $exception->getErrorCode());
    }

    public function testOrderNotExists(): void
    {
        $exception = TransactionDataException::oderNotExists('tr_abc456');

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
        $this->assertSame(TransactionDataException::TRANSACTION_ORDER_NOT_FOUND, $exception->getErrorCode());
    }

    public function testOrderWithoutDeliveries(): void
    {
        $exception = TransactionDataException::orderWithoutDeliveries('order-111');

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
        $this->assertSame(TransactionDataException::ORDER_WITHOUT_DELIVERIES, $exception->getErrorCode());
    }

    public function testOrderWithoutLanguage(): void
    {
        $exception = TransactionDataException::orderWithoutLanguage('order-222');

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
        $this->assertSame(TransactionDataException::ORDER_WITHOUT_LANGUAGE, $exception->getErrorCode());
    }

    public function testOrderWithoutCurrency(): void
    {
        $exception = TransactionDataException::orderWithoutCurrency('order-333');

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
        $this->assertSame(TransactionDataException::ORDER_WITHOUT_CURRENCY, $exception->getErrorCode());
    }

    public function testOrderDeliveryWithoutShippingAddress(): void
    {
        $exception = TransactionDataException::orderDeliveryWithoutShippingAddress('order-444', 'delivery-001');

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
        $this->assertSame(TransactionDataException::ORDER_DELIVERY_WITHOUT_ADDRESS, $exception->getErrorCode());
    }

    public function testOrderWithoutCustomer(): void
    {
        $exception = TransactionDataException::orderWithoutCustomer('order-555');

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
    }

    public function testOrderWithoutSalesChannel(): void
    {
        $exception = TransactionDataException::orderWithoutSalesChannel('order-666');

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
    }

    public function testOrderWithoutBillingAddress(): void
    {
        $exception = TransactionDataException::orderWithoutBillingAddress('order-777');

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
        $this->assertSame(TransactionDataException::ORDER_WITHOUT_BILLING_ADDRESS, $exception->getErrorCode());
    }

    public function testFactoryMethodsAcceptPreviousException(): void
    {
        $previous = new \RuntimeException('root cause');

        $exception = TransactionDataException::transactionNotFound('tr_abc', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
