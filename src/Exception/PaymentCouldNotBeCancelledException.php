<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class PaymentCouldNotBeCancelledException extends ShopwareHttpException
{
    /**
     * @param string $molliePaymentId
     * @param array<string,mixed> $parameters
     * @param null|\Throwable $previous
     */
    public function __construct(string $molliePaymentId, array $parameters = [], \Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Mollie payment (%s) could not be cancelled.', $molliePaymentId),
            $parameters,
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__MOLLIE_PAYMENT_COULD_NOT_BE_CANCELLED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
