<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

enum CaptureStatus: string
{
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
}
