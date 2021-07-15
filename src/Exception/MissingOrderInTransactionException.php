<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MissingOrderInTransactionException extends ShopwareHttpException
{
    public function __construct(string $id)
    {
        $message = sprintf('An order for transaction with id %s could not be found', $id);
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__ORDER_NOT_FOUND_BY_TRANSACTION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
