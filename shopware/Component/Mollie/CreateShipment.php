<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class CreateShipment implements \JsonSerializable
{
    public function __construct(
        private ShippingItemCollection $lines,
        private ?Tracking $tracking = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $data = ['lines' => $this->lines];

        if ($this->tracking !== null && $this->tracking->getCode() !== '') {
            $data['tracking'] = $this->tracking;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return json_decode((string) json_encode($this), true);
    }
}
