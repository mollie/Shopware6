<?php

namespace Kiener\MolliePayments\Exception;

class MollieShipmentTrackingInfoCouldNotBeUpdatedException extends \Shopware\Core\Framework\ShopwareHttpException
{
    public function __construct(string $mollieOrderID, string $mollieShipmentId, array $parameters = [], ?\Throwable $e = null)
    {
        $message = sprintf(
            'Mollie tracking info for shipment (%s) from order (%s) could not be shipped',
            $mollieOrderID,
            $mollieShipmentId
        );

        parent::__construct($message, $parameters, $e);
    }

    public function getErrorCode(): string
    {
        return 'MOLLIE_PAYMENTS__SHIPMENT_TRACKING_COULD_NOT_BE_UPDATED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
