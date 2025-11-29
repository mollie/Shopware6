<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class CaptureMode extends AbstractEnum
{
    public const MANUAL = 'manual';
    public const AUTOMATIC = 'automatic';

    /**
     * @return string[]
     */
    protected function getPossibleValues(): array
    {
        return [
            self::MANUAL,
            self::AUTOMATIC,
        ];
    }
}
