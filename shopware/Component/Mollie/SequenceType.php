<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

enum SequenceType: string
{
    case ONEOFF = 'oneoff';
    case FIRST = 'first';
    case RECURRING = 'recurring';
}
