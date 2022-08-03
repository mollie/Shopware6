<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MissingCartServiceException extends ShopwareHttpException
{
    public function __construct(Throwable $previous = null)
    {
        $message = 'CartService of Shopware not found!';
        $parameters = [];

        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__MISSING_CART_SERVICE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
