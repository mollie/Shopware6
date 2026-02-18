<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

enum Mode: string
{
    case LIVE = 'live';
    case TEST = 'test';
}
