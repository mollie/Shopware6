<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Struct\MollieApi\ShipmentTrackingInfoStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;

class TrackingInfoStructFactory
{
    public function createFromDelivery(OrderDeliveryEntity $orderDeliveryEntity):?ShipmentTrackingInfoStruct
    {
        $trackingCodes = $orderDeliveryEntity->getTrackingCodes();
        $shippingMethod = $orderDeliveryEntity->getShippingMethod();
        if ($shippingMethod === null) {
            return null;
        }
        if (count($trackingCodes) !== 1) {
            return null;
        }

        return $this->create((string)$shippingMethod->getName(), $trackingCodes[0], (string)$shippingMethod->getTrackingUrl());
    }

    public function create(string $trackingCarrier, string $trackingCode, string $trackingUrl): ?ShipmentTrackingInfoStruct
    {
        if (empty($trackingCarrier) && empty($trackingCode)) {
            return null;
        }

        if (empty($trackingCarrier)) {
            throw new \InvalidArgumentException('Missing Argument for Tracking Carrier!');
        }

        if (empty($trackingCode)) {
            throw new \InvalidArgumentException('Missing Argument for Tracking Code!');
        }

        $trackingUrl = trim($trackingUrl . $trackingCode);

        if (filter_var($trackingUrl, FILTER_VALIDATE_URL) === false) {
            $trackingUrl = '';
        }

        /**
         * following characters are not allowed in the tracking URL {,},<,>,#
         */
        if (preg_match_all('/[{}<>#]/m', $trackingUrl)) {
            $trackingUrl = '';
        }

        return new ShipmentTrackingInfoStruct($trackingCarrier, $trackingCode, $trackingUrl);
    }
}
