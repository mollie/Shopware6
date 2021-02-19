<?php

namespace Kiener\MolliePayments\Exception;


use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class UnexpectedObjectType extends ShopwareHttpException
{
    public function __construct(string $expectedType)
    {
        $message = sprintf('Expected type should be %s', $expectedType);
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'CORE__UNEXPECTED_OBJECT_TYPE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
