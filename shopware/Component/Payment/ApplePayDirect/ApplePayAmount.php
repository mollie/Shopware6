<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect;

final class ApplePayAmount implements \Stringable, \JsonSerializable
{
    public function __construct(private float $amount)
    {
    }

    public function __toString(): string
    {
        return (string) $this->amount;
    }

    public function jsonSerialize(): mixed
    {
        return (string) $this;
    }
}
