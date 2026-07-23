<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Shipment;

use Mollie\Shopware\Component\Mollie\Tracking;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the tracking information for a shipment. Explicit carrier/code/url from the request take
 * precedence; otherwise carrier and url are derived from the order's shipping method. Holds no
 * dependencies.
 */
final class ShipmentTrackingResolver
{
    /**
     * @param list<string> $targetLineItemIds
     */
    public function resolve(Request $request, OrderDeliveryCollection $deliveries, array $targetLineItemIds): ?Tracking
    {
        $requestCarrier = (string) $request->get('trackingCarrier', '');
        $requestCode = (string) $request->get('trackingCode', '');
        $requestUrl = (string) $request->get('trackingUrl', '');

        if ($requestCarrier !== '') {
            return new Tracking($requestCarrier, $requestCode, $requestUrl);
        }

        foreach ($deliveries as $delivery) {
            $positions = $delivery->getPositions();
            if ($positions === null) {
                continue;
            }

            $belongs = false;
            foreach ($positions as $position) {
                if (in_array($position->getOrderLineItemId(), $targetLineItemIds, true)) {
                    $belongs = true;
                    break;
                }
            }

            if ($belongs === false) {
                continue;
            }

            $shippingMethod = $delivery->getShippingMethod();
            if ($shippingMethod === null) {
                continue;
            }

            $carrier = (string) $shippingMethod->getName();
            if ($carrier === '') {
                return null;
            }

            $code = $requestCode;
            if ($code === '') {
                $codes = array_values(array_filter($delivery->getTrackingCodes()));
                if (count($codes) !== 1) {
                    return null;
                }
                $code = $codes[0];
            }

            if (mb_strlen($code) > 99) {
                return null;
            }

            $urlTemplate = (string) $shippingMethod->getTrackingUrl();
            if (str_contains($urlTemplate, '%s%')) {
                $urlTemplate = '';
            }
            $url = $urlTemplate !== '' ? trim(sprintf($urlTemplate, $code)) : '';
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                $url = '';
            }

            return new Tracking($carrier, $code, $url);
        }

        return null;
    }
}
