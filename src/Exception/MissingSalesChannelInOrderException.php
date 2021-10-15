<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MissingSalesChannelInOrderException extends ShopwareHttpException
{
    public function __construct(string $orderNumber)
    {
        $message = sprintf('Could not extract SalesChannel from order (%s)', $orderNumber);
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__MISSING_SALESCHANNEL_IN_ORDER';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
