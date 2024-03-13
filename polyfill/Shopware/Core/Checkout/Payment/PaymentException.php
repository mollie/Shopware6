<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;


if (class_exists(__NAMESPACE__ . '/PaymentException')) {
    return;
}

use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;

class PaymentException
{
    public static function asyncFinalizeInterrupted(string $orderTransactionId, string $errorMessage, ?\Throwable $e = null): self
    {
        return new AsyncPaymentFinalizeException($orderTransactionId, $errorMessage, $e);
    }

    public static function customerCanceled(string $orderTransactionId, string $additionalInformation, ?\Throwable $e = null): self
    {
        return new CustomerCanceledAsyncPaymentException($orderTransactionId, $additionalInformation, $e);
    }
}