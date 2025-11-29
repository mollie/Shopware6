<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class SequenceType extends AbstractEnum
{
    public const DEFAULT = 'oneoff';

    public function __construct(string $value = self::DEFAULT)
    {
        parent::__construct($value);
    }

    /**
     * @return string[]
     */
    protected function getPossibleValues(): array
    {
        return [
            'oneoff',
            'first',
            'recurring',
        ];
    }
}
