<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CouldNotFetchTransactionException extends ShopwareHttpException
{
    public function __construct(string $id)
    {
        $message = sprintf('Could not fetch transaction with id %s from database', $id);
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__TRANSACTION_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
