<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class SalesChannelPaymentMethodsException extends ShopwareHttpException
{
    /**
     * @param array<mixed> $parameters
     */
    public function __construct(string $salesChannelName, array $parameters = [], ?\Throwable $previous = null)
    {
        $message = sprintf('Could not extract payment methods from sales channel %s', $salesChannelName);
        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__PAYMENT_METHODS_COULD_NOT_BE_EXTRACTED_FROM_SALES_CHANNEL';
    }
}
