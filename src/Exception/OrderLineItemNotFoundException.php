<?php

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderLineItemNotFoundException extends ShopwareHttpException
{
    /**
     * @param string $identifier
     * @param array<string,mixed> $parameters
     * @param null|\Throwable $previous
     */
    public function __construct(string $identifier, array $parameters = [], \Throwable $previous = null)
    {
        $message = sprintf('Order lineitem with identifier %s could not be found', $identifier);
        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__ORDER_LINEITEM_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
