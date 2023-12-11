<?php

namespace Kiener\MolliePayments\Components\ShipmentManager\Models;

class TrackingData
{

    /**
     * @var string
     */
    private $carrier;

    /**
     * @var string
     */
    private $code;

    /**
     * @var string
     */
    private $trackingUrl;

    /**
     * @param string $carrier
     * @param string $code
     * @param string $trackingUrl
     */
    public function __construct(string $carrier, string $code, string $trackingUrl)
    {
        $this->carrier = $carrier;
        $this->code = $code;
        $this->trackingUrl = $trackingUrl;
    }

    /**
     * @return string
     */
    public function getCarrier(): string
    {
        return $this->carrier;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getTrackingUrl(): string
    {
        return $this->trackingUrl;
    }
}
