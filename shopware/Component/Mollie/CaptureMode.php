<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

enum CaptureMode: string
{
    case MANUAL = 'manual';
    case AUTOMATIC = 'automatic';
}
