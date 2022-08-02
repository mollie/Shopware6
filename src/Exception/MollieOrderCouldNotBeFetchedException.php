<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MollieOrderCouldNotBeFetchedException extends ShopwareHttpException
{
    /**
     * @param string $mollieOrderID
     * @param array<string,mixed> $parameters
     * @param null|\Throwable $previous
     */
    public function __construct(string $mollieOrderID, array $parameters = [], \Throwable $previous = null)
    {
        $message = sprintf('Mollie order (%s) could not be fetched', $mollieOrderID);
        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__ORDER_COULD_NOT_BE_FETCHED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
