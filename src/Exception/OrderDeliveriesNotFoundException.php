<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderDeliveriesNotFoundException extends ShopwareHttpException
{
    /**
     * @param array<string,mixed> $parameters
     */
    public function __construct(string $id, array $parameters = [], ?\Throwable $previous = null)
    {
        $message = sprintf('Deliveries of order %s could not be fetched', $id);
        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__DELIVERIES_NOT_FOUND_IN_ORDER';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
