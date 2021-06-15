<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MollieOrderCouldNotBeCancelled extends ShopwareHttpException
{
    public function __construct(string $id, array $parameters = [], \Throwable $previous = null)
    {
        $message = sprintf('Mollie order with id %s could not be cancelled', $id);
        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__ORDER_COULD_NOT_BE_CANCELLED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
