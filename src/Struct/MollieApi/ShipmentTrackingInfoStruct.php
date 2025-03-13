<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\MollieApi;

use Shopware\Core\Framework\Struct\Struct;

class ShipmentTrackingInfoStruct extends Struct
{
    /** @var string */
    protected $carrier;

    /** @var string */
    protected $code;

    /** @var string */
    protected $url;

    public function __construct(
        string $carrier,
        string $code,
        string $url = ''
    ) {
        $this->carrier = $carrier;
        $this->code = $code;
        $this->url = $url;
    }

    /**
     * @return array<string, string>
     */
    public function toArray()
    {
        return [
            'carrier' => $this->getCarrier(),
            'code' => $this->getCode(),
            'url' => $this->getUrl(),
        ];
    }

    public function getCarrier(): string
    {
        return $this->carrier;
    }

    public function setCarrier(string $carrier): void
    {
        $this->carrier = $carrier;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }
}
