<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Route;

use Mollie\Shopware\Component\Payment\Route\WebhookException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(WebhookException::class)]
final class WebhookExceptionTest extends TestCase
{
    #[DataProvider('provideExceptions')]
    public function testExceptionHasCorrectErrorCodeAndStatus(
        WebhookException $ex,
        string $expectedCode,
        int $expectedStatus,
    ): void {
        $this->assertSame($expectedCode, $ex->getErrorCode());
        $this->assertSame($expectedStatus, $ex->getStatusCode());
    }

    public static function provideExceptions(): array
    {
        $cause = new \RuntimeException('cause');

        return [
            'transaction-without-order' => [
                WebhookException::transactionWithoutOrder('tx-1'),
                WebhookException::TRANSACTION_WITHOUT_ORDER,
                Response::HTTP_BAD_REQUEST,
            ],
            'transaction-without-payment-method' => [
                WebhookException::transactionWithoutPaymentMethod('tx-2'),
                WebhookException::TRANSACTION_WITHOUT_PAYMENT_METHOD,
                Response::HTTP_BAD_REQUEST,
            ],
            'transaction-without-mollie-payment' => [
                WebhookException::transactionWithoutMolliePayment('tx-3'),
                WebhookException::TRANSACTION_WITHOUT_MOLLIE_PAYMENT,
                Response::HTTP_BAD_REQUEST,
            ],
            'payment-without-method' => [
                WebhookException::paymentWithoutMethod('tx-4', 'pay-4'),
                WebhookException::PAYMENT_WITHOUT_METHOD,
                Response::HTTP_BAD_REQUEST,
            ],
            'order-without-state' => [
                WebhookException::orderWithoutState('tx-5', 'ORD-5'),
                WebhookException::ORDER_WITHOUT_STATE,
                Response::HTTP_BAD_REQUEST,
            ],
            'payment-status-change-failed' => [
                WebhookException::paymentStatusChangeFailed('tx-6', 'ORD-6', $cause),
                WebhookException::PAYMENT_STATUS_CHANGE_FAILED,
                Response::HTTP_BAD_REQUEST,
            ],
            'order-status-change-failed' => [
                WebhookException::orderStatusChangeFailed('tx-7', 'ORD-7', $cause),
                WebhookException::ORDER_STATUS_CHANGE_FAILED,
                Response::HTTP_BAD_REQUEST,
            ],
            'payment-method-change-failed' => [
                WebhookException::paymentMethodChangeFailed('tx-8', 'ORD-8', $cause),
                WebhookException::PAYMENT_METHOD_CHANGE_FAILED,
                Response::HTTP_BAD_REQUEST,
            ],
        ];
    }
}
