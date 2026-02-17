<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class Interval implements \Stringable
{
    public function __construct(private int $intervalValue, private IntervalUnit $intervalUnit)
    {
    }

    public function __toString(): string
    {
        return $this->intervalValue . ' ' . $this->intervalUnit->value;
    }

    public static function fromString(string $interval): self
    {
        $intervalValues = explode(' ', $interval);
        $intervalUnit = str_replace('ss','s',$intervalValues[1] . 's');

        return new self((int) $intervalValues[0], IntervalUnit::from($intervalUnit));
    }

    public function getIntervalValue(): int
    {
        return $this->intervalValue;
    }

    public function getIntervalUnit(): IntervalUnit
    {
        return $this->intervalUnit;
    }
}
