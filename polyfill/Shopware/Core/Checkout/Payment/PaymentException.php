<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

if (class_exists(PaymentException::class)) {
    return;
}

use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;

class PaymentException extends \Exception
{
    public static function asyncFinalizeInterrupted(string $orderTransactionId, string $errorMessage, ?\Throwable $e = null): AsyncPaymentFinalizeException
    {
        return new AsyncPaymentFinalizeException($orderTransactionId, $errorMessage, $e);
    }

    public static function customerCanceled(string $orderTransactionId, string $additionalInformation, ?\Throwable $e = null): CustomerCanceledAsyncPaymentException
    {
        return new CustomerCanceledAsyncPaymentException($orderTransactionId, $additionalInformation, $e);
    }
}
