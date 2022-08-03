<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderNumberNotFoundException extends ShopwareHttpException
{
    /**
     * @param string $orderNumber
     * @param array<string,mixed> $parameters
     * @param null|\Throwable $previous
     */
    public function __construct(string $orderNumber, array $parameters = [], \Throwable $previous = null)
    {
        $message = sprintf('Order with number %s could not be found', $orderNumber);
        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__ORDER_NOT_FOUND_BY_NUMBER';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
