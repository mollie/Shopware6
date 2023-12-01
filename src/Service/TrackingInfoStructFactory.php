<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Struct\MollieApi\ShipmentTrackingInfoStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;

class TrackingInfoStructFactory
{
    /**
     * Mollie throws an error with length >= 100
     */
    const MAX_TRACKING_CODE_LENGTH = 99;

    public function createFromDelivery(OrderDeliveryEntity $orderDeliveryEntity): ?ShipmentTrackingInfoStruct
    {
        $trackingCodes = $orderDeliveryEntity->getTrackingCodes();
        $shippingMethod = $orderDeliveryEntity->getShippingMethod();
        if ($shippingMethod === null) {
            return null;
        }
        /**
         * Currently we create one shipping in mollie for one order. one shipping object can have only one tracking code.
         * When we have multiple Tracking Codes, we do not know which tracking code we should send to mollie. So we just dont send any tracking information at all
         *
         * https://docs.mollie.com/reference/v2/shipments-api/create-shipment
         */
        if (count($trackingCodes) !== 1) {
            return null;
        }

        return $this->createInfoStruct((string)$shippingMethod->getName(), $trackingCodes[0], (string)$shippingMethod->getTrackingUrl());
    }

    public function create(string $trackingCarrier, string $trackingCode, string $trackingUrl): ?ShipmentTrackingInfoStruct
    {
        return $this->createInfoStruct($trackingCarrier, $trackingCode, $trackingUrl);
    }

    private function createInfoStruct(string $trackingCarrier, string $trackingCode, string $trackingUrl): ?ShipmentTrackingInfoStruct
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

        # we just have to completely remove those codes, so that no tracking happens, but a shipping works.
        # still, if we find multiple codes (because separators exist), then we use the first one only
        if (mb_strlen($trackingCode) > self::MAX_TRACKING_CODE_LENGTH) {
            if (strpos($trackingCode, ',') !== false) {
                $trackingCode = trim(explode(',', $trackingCode)[0]);
            } elseif (strpos($trackingCode, ';') !== false) {
                $trackingCode = trim(explode(';', $trackingCode)[0]);
            }

            # if we are still too long, then simply remove the code
            if (mb_strlen($trackingCode) > self::MAX_TRACKING_CODE_LENGTH) {
                return new ShipmentTrackingInfoStruct($trackingCarrier, '', '');
            }
        }


        $trackingUrl = trim(sprintf($trackingUrl, $trackingCode));

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
