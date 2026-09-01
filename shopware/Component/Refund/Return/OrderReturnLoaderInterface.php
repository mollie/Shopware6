<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Return;

use Mollie\Shopware\Component\Refund\Return\Struct\OrderReturnStruct;
use Shopware\Core\Framework\Context;

interface OrderReturnLoaderInterface
{
    /**
     * False when SwagCommercial is not installed, so there is no Return Management to react to.
     * Kept apart from load(): the handler logs a disabled feature differently from a return it
     * cannot find.
     */
    public function isAvailable(): bool;

    public function load(string $returnId, Context $context): ?OrderReturnStruct;
}
