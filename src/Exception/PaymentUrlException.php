<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Checkout\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

class PaymentUrlException extends PaymentException
{
    public function __construct(string $orderTransactionId, string $errorMessage)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            $this->getErrorCode(),
            'Could not create a Mollie payment url for transaction {{transactionId}} due to the following error: {{ errorMessage }}',
            ['transactionId' => $orderTransactionId,
                'errorMessage' => $errorMessage
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__MOLLIE_COULD_NOT_CREATE_PAYMENT_URL';
    }
}
