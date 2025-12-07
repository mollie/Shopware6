<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

enum TerminalModel: string
{
    case A35 = 'A35';
    case A77 = 'A77';
    case A920 = 'A920';
    case A920_PRO = 'A920Pro';
    case IM300 = 'IM300';
    case TAP = 'Tap';
}
