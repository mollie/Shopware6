<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

/**
 * Since we support PHP 8.0 real enums are not possible YET, so we have to use a workaround
 */
abstract class AbstractEnum implements \Stringable
{
    public function __construct(private string $value)
    {
        if (! \in_array($this->value, $this->getPossibleValues(), true)) {
            throw new \InvalidArgumentException(sprintf('%s is not a valid value. possible values are %s', $this->value, implode(', ', $this->getPossibleValues())));
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @return array<mixed>
     */
    abstract protected function getPossibleValues(): array;
}
