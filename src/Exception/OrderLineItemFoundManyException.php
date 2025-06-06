<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderLineItemFoundManyException extends ShopwareHttpException
{
    /**
     * @param array<string,mixed> $parameters
     */
    public function __construct(string $identifier, array $parameters = [], ?\Throwable $previous = null)
    {
        $message = sprintf('Too many order line items with identifier %s were found. Please use a more unique identifier', $identifier);
        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__ORDER_LINEITEM_FOUND_TOO_MANY';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
