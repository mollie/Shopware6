<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;


use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class OrderCurrencyNotFound extends ShopwareHttpException
{
    public function __construct(string $id, array $parameters = [], \Throwable $previous = null)
    {
        $message = sprintf('Currency of order %s could not be fetched', $id);
        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__CURRENCY_NOT_FOUND_IN_ORDER';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
