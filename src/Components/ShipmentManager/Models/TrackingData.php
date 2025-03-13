<?php
declare(strict_types=1);

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

    public function __construct(string $carrier, string $code, string $trackingUrl)
    {
        $this->carrier = $carrier;
        $this->code = $code;
        $this->trackingUrl = $trackingUrl;
    }

    public function getCarrier(): string
    {
        return $this->carrier;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getTrackingUrl(): string
    {
        return $this->trackingUrl;
    }

    /**
     * @return array<string,string>
     */
    public function toArray(): array
    {
        return [
            'carrier' => $this->carrier,
            'code' => $this->code,
            'tracking_url' => $this->trackingUrl,
        ];
    }
}
