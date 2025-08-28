<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Components\ShipmentManager\Exceptions\NoDeliveriesFoundException;
use Kiener\MolliePayments\Struct\MollieApi\ShipmentTrackingInfoStruct;
use Kiener\MolliePayments\Traits\StringTrait;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Throwable;

class TrackingInfoStructFactory
{
    use StringTrait;

    /**
     * Mollie throws an error with length >= 100
     */
    public const MAX_TRACKING_CODE_LENGTH = 99;

    /**
     * @var UrlParsingService
     */
    private $urlParsingService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        UrlParsingService $urlParsingService,
        LoggerInterface $logger
    ) {
        $this->urlParsingService = $urlParsingService;
        $this->logger = $logger;
    }

    /**
     * @throws NoDeliveriesFoundException
     */
    public function trackingFromOrder(OrderEntity $order): ?ShipmentTrackingInfoStruct
    {
        // automatically extract from order
        $deliveries = $order->getDeliveries();

        if (! $deliveries instanceof OrderDeliveryCollection || $deliveries->count() === 0) {
            throw new NoDeliveriesFoundException('No deliveries found for order with ID ' . $order->getId() . '!');
        }
        /** @var OrderDeliveryEntity $orderDeliveryEntity */
        $orderDeliveryEntity = $deliveries->first();
        if ($orderDeliveryEntity === null) {
            throw new NoDeliveriesFoundException('No deliveries found for order with ID ' . $order->getId() . '!');
        }
        $trackingCodes = $orderDeliveryEntity->getTrackingCodes();
        $shippingMethod = $orderDeliveryEntity->getShippingMethod();

        if ($shippingMethod === null) {
            return null;
        }
        $trackingCodes = array_filter($trackingCodes);
        /*
         * Currently we create one shipping in mollie for one order. one shipping object can have only one tracking code.
         * When we have multiple Tracking Codes, we do not know which tracking code we should send to mollie. So we just dont send any tracking information at all
         *
         * https://docs.mollie.com/reference/v2/shipments-api/create-shipment
         */
        if (count($trackingCodes) !== 1) {
            return null;
        }

        $this->logger->info(sprintf('Creating tracking information for shipment with shipping method %s. Order: "%s"', $shippingMethod->getName(), $order->getOrderNumber()));
        try {
            return $this->createInfoStruct(
                (string) $shippingMethod->getName(),
                (string) $trackingCodes[0],
                (string) $shippingMethod->getTrackingUrl()
            );
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage());

            return null;
        }
    }

    public function create(string $trackingCarrier, string $trackingCode, string $trackingUrl): ?ShipmentTrackingInfoStruct
    {
        return $this->createInfoStruct($trackingCarrier, $trackingCode, $trackingUrl);
    }

    private function createInfoStruct(string $trackingCarrier, string $trackingCode, string $trackingUrl): ?ShipmentTrackingInfoStruct
    {
        $this->logger->debug('Creating tracking information for shipment.', [
            'trackingCarrier' => $trackingCarrier,
            'trackingCode' => $trackingCode,
            'trackingUrl' => $trackingUrl,
        ]);

        if (empty($trackingCarrier) && empty($trackingCode)) {
            $this->logger->debug('No tracking information provided for shipment.');

            return null;
        }

        if (empty($trackingCarrier)) {
            throw new \InvalidArgumentException('Missing Argument for Tracking Carrier!');
        }

        if (empty($trackingCode)) {
            throw new \InvalidArgumentException('Missing Argument for Tracking Code!');
        }

        // determine if the provided tracking code is actually a tracking URL
        if (empty($trackingUrl) === true && $this->urlParsingService->isUrl($trackingCode)) {
            $this->logger->debug('Tracking code is a URL, parsing tracking code from URL.', [
                'trackingCode' => $trackingCode,
                'trackingUrl' => $trackingUrl,
            ]);

            [$trackingCode, $trackingUrl] = $this->urlParsingService->parseTrackingCodeFromUrl($trackingCode);

            $this->logger->debug('Parsed tracking code from URL.', [
                'trackingCode' => $trackingCode,
                'trackingUrl' => $trackingUrl,
            ]);
        }

        // we just have to completely remove those codes, so that no tracking happens, but a shipping works.
        // still, if we find multiple codes (because separators exist), then we use the first one only
        if (mb_strlen($trackingCode) > self::MAX_TRACKING_CODE_LENGTH) {
            $this->logger->debug('Tracking code is too long, truncating.', ['trackingCode' => $trackingCode]);
            if (strpos($trackingCode, ',') !== false) {
                $trackingCode = trim(explode(',', $trackingCode)[0]);
            } elseif (strpos($trackingCode, ';') !== false) {
                $trackingCode = trim(explode(';', $trackingCode)[0]);
            }

            $this->logger->debug('Truncated tracking code.', ['trackingCode' => $trackingCode]);

            // if we are still too long, then simply remove the code
            if (mb_strlen($trackingCode) > self::MAX_TRACKING_CODE_LENGTH) {
                $this->logger->warning('Tracking code is still too long, removing.', ['trackingCode' => $trackingCode]);

                return null;
            }
        }

        if ($trackingCode === '') {
            $this->logger->warning('Tracking Code is empty');

            return null;
        }

        // had the use case of this pattern, and it broke the sprintf below
        if ($this->stringContains($trackingUrl, '%s%')) {
            $trackingUrl = '';
        }

        $trackingUrl = trim(sprintf($trackingUrl, $trackingCode));

        if ($this->urlParsingService->isUrl($trackingUrl) === false) {
            return new ShipmentTrackingInfoStruct($trackingCarrier, $trackingCode, '');
        }

        return new ShipmentTrackingInfoStruct($trackingCarrier, $trackingCode, $trackingUrl);
    }
}
