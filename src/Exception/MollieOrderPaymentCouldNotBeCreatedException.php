<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MollieOrderPaymentCouldNotBeCreatedException extends ShopwareHttpException
{
    /**
     * @param string $id
     * @param array<string,mixed> $parameters
     * @param null|\Throwable $previous
     */
    public function __construct(string $id, array $parameters = [], \Throwable $previous = null)
    {
        $message = sprintf('Could not create a new payment for mollie order (%s)', $id);
        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__NEW_PAYMENT_FOR_ORDER_COULD_NOT_BE_CREATED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
