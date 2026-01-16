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

    public function getIntervalValue(): int
    {
        return $this->intervalValue;
    }

    public function getIntervalUnit(): IntervalUnit
    {
        return $this->intervalUnit;
    }
}
