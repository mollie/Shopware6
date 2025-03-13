<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Refund\Item;

interface RefundItemType
{
    public const FULL = 'full';
    public const PARTIAL = 'partial';
}
