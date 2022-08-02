<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Checkout\Payment\Exception\PaymentProcessException;

class PaymentUrlException extends PaymentProcessException
{
    public function __construct(string $orderTransactionId, string $errorMessage)
    {
        parent::__construct(
            $orderTransactionId,
            'Could not create a Mollie payment url due to the following error: {{ errorMessage }}',
            ['errorMessage' => $errorMessage]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__MOLLIE_COULD_NOT_CREATE_PAYMENT_URL';
    }
}
