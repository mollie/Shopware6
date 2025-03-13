<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class MollieOrderExpiredException extends ShopwareHttpException
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(string $mollieOrderID, array $parameters = [], ?\Throwable $e = null)
    {
        $message = 'Mollie order {mollieId} is expired';
        $parameters['mollieId'] = $mollieOrderID;
        parent::__construct($message, $parameters, $e);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__ORDER_IS_EXPIRED';
    }
}
