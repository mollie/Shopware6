<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidMollieOrderException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Mollie order is invalid.');
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__MOLLIE_ORDER_INVALID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
