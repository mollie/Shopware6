<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Route;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

final class PaymentException extends HttpException
{
    public const TRANSACTION_WITHOUT_ORDER = 'TRANSACTION_WITHOUT_ORDER';
    public const TRANSACTION_WITHOUT_PAYMENT_METHOD = 'TRANSACTION_WITHOUT_PAYMENT_METHOD';
    public const TRANSACTION_WITHOUT_MOLLIE_PAYMENT = 'TRANSACTION_WITHOUT_MOLLIE_PAYMENT';
    public const NEW_PAYMENT_METHOD_NOT_FOUND = 'NEW_PAYMENT_METHOD_NOT_FOUND';
    public const PAYMENT_WITHOUT_METHOD = 'PAYMENT_WITHOUT_METHOD';

    public static function transactionWithoutOrder(string $transactionId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TRANSACTION_WITHOUT_ORDER,
            'Shopware order not found for TransactionId: {transactionId}',[
                'transactionId' => $transactionId,
            ]
        );
    }

    public static function transactionWithoutPaymentMethod(string $transactionId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TRANSACTION_WITHOUT_PAYMENT_METHOD,
            'Transaction {transactionId} was loaded without payment method',[
                'transactionId' => $transactionId,
            ]
        );
    }

    public static function transactionWithoutMolliePayment(string $transactionId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TRANSACTION_WITHOUT_MOLLIE_PAYMENT,
            'Transaction {transactionId} does not have mollie custom fields',[
                'transactionId' => $transactionId,
            ]
        );
    }

    public static function paymentMethodNotFound(string $transactionId, string $paymentMethod): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NEW_PAYMENT_METHOD_NOT_FOUND,
            'Transaction {transactionId} has {paymentMethod} in mollie API, but this method is not active in shop',[
                'transactionId' => $transactionId,
                'paymentMethod' => $paymentMethod,
            ]
        );
    }

    public static function paymentWithoutMethod(string $transactionId, string $paymentId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_WITHOUT_METHOD,
            'Mollie payment {paymentId} is without payment method in transaction {transactionId}',[
                'transactionId' => $transactionId,
                'paymentId' => $paymentId,
            ]
        );
    }
}
