<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderLineItemNotFoundException extends ShopwareHttpException
{
    /**
     * @param array<string,mixed> $parameters
     */
    public function __construct(string $identifier, array $parameters = [], \Throwable $previous = null)
    {
        if (empty($identifier)) {
            $message = 'Could not find an OrderLineItem. No identifier/productNumber provided';
        } else {
            $message = sprintf('OrderLineItem with identifier: "%s" could not be found', $identifier);
        }

        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__ORDER_LINEITEM_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
