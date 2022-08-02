<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MissingMollieOrderIdException extends ShopwareHttpException
{
    public function __construct(string $orderNumber, Throwable $previous = null)
    {
        $message = sprintf('The Mollie id for order %s could not be found', $orderNumber);
        parent::__construct($message, [], $previous);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__MOLLIE_ORDER_ID_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
