<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class PayPalExpressSettings extends Struct
{
    private int $shape = 0;
    private int $style = 0;
    /**
     * @var array<string>
     */
    private array $restrictions = [];

    public function __construct(private bool $enabled)
    {
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function getShape(): int
    {
        return $this->shape;
    }

    public function setShape(int $shape): void
    {
        $this->shape = $shape;
    }

    public function getStyle(): int
    {
        return $this->style;
    }

    public function setStyle(int $style): void
    {
        $this->style = $style;
    }

    /**
     * @return string[]
     */
    public function getRestrictions(): array
    {
        return $this->restrictions;
    }

    /**
     * @param array<string> $restrictions
     */
    public function setRestrictions(array $restrictions): void
    {
        $this->restrictions = $restrictions;
    }

    public function isEnabled(): bool
    {
        return $this->enabled === true;
    }
}
