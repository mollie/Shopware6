<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class Tracking implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        private string $carrier,
        private string $code,
        private string $url,
    ) {
    }

    public function getCarrier(): string
    {
        return $this->carrier;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
