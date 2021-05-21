<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;


use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class OrderCustomerNotFound extends ShopwareHttpException
{
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
