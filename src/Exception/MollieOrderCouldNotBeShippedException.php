<?php

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MollieOrderCouldNotBeShippedException extends ShopwareHttpException
{
    /**
     * @param string $mollieOrderID
     * @param array<string,mixed> $parameters
     * @param null|\Throwable $e
     */
    public function __construct(string $mollieOrderID, array $parameters = [], ?\Throwable $e = null)
    {
        $message = sprintf('Mollie order (%s) could not be shipped', $mollieOrderID);
        parent::__construct($message, $parameters, $e);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__ORDER_COULD_NOT_BE_SHIPPED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
