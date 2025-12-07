<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

enum TerminalStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
}
