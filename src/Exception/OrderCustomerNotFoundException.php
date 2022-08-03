<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderCustomerNotFoundException extends ShopwareHttpException
{
    /**
     * @param string $id
     * @param array<string,mixed> $parameters
     * @param null|\Throwable $previous
     */
    public function __construct(string $id, array $parameters = [], \Throwable $previous = null)
    {
        $message = sprintf('Customer of order %s could not be found', $id);
        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__CUSTOMER_NOT_FOUND_IN_ORDER';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
