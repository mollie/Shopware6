<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect\Struct;

final class ApplePayAmount implements \JsonSerializable
{
    private const PRECISION = 2;

    private float $value;

    public function __construct(float $value)
    {
        $this->value = round($value, self::PRECISION);
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }
}
