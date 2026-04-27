<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Exception;

use Mollie\Shopware\Component\Payment\Route\WebhookException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(WebhookException::class)]
final class WebhookExceptionTest extends TestCase
{
    public function testTransactionWithoutOrder(): void
    {
        $exception = WebhookException::transactionWithoutOrder('tx-001');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(WebhookException::TRANSACTION_WITHOUT_ORDER, $exception->getErrorCode());
    }

    public function testTransactionWithoutPaymentMethod(): void
    {
        $exception = WebhookException::transactionWithoutPaymentMethod('tx-002');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(WebhookException::TRANSACTION_WITHOUT_PAYMENT_METHOD, $exception->getErrorCode());
    }

    public function testTransactionWithoutMolliePayment(): void
    {
        $exception = WebhookException::transactionWithoutMolliePayment('tx-003');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(WebhookException::TRANSACTION_WITHOUT_MOLLIE_PAYMENT, $exception->getErrorCode());
    }

    public function testPaymentWithoutMethod(): void
    {
        $exception = WebhookException::paymentWithoutMethod('tx-004', 'tr_abc');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(WebhookException::PAYMENT_WITHOUT_METHOD, $exception->getErrorCode());
    }

    public function testOrderWithoutState(): void
    {
        $exception = WebhookException::orderWithoutState('tx-005', 'ORDER-12345');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(WebhookException::ORDER_WITHOUT_STATE, $exception->getErrorCode());
    }

    public function testPaymentStatusChangeFailed(): void
    {
        $previous = new \RuntimeException('state machine error');
        $exception = WebhookException::paymentStatusChangeFailed('tx-006', 'ORDER-67890', $previous);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(WebhookException::PAYMENT_STATUS_CHANGE_FAILED, $exception->getErrorCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testOrderStatusChangeFailed(): void
    {
        $previous = new \RuntimeException('order state machine error');
        $exception = WebhookException::orderStatusChangeFailed('tx-007', 'ORDER-11111', $previous);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(WebhookException::ORDER_STATUS_CHANGE_FAILED, $exception->getErrorCode());
    }

    public function testPaymentMethodChangeFailed(): void
    {
        $previous = new \RuntimeException('method change failed');
        $exception = WebhookException::paymentMethodChangeFailed('tx-008', 'ORDER-22222', $previous);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(WebhookException::PAYMENT_METHOD_CHANGE_FAILED, $exception->getErrorCode());
    }
}
