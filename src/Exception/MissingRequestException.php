<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MissingRequestException extends ShopwareHttpException
{
    public function __construct(Throwable $previous = null)
    {
        $message = 'No request available on the stack.';
        $parameters = [];

        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__MISSING_REQUEST';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
