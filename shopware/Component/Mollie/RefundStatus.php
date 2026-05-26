<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

enum RefundStatus: string
{
    case Queued = 'queued';
    case Pending = 'pending';
    case Processing = 'processing';
    case Refunded = 'refunded';
    case Failed = 'failed';
    case Canceled = 'canceled';
}
