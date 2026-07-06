<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin\Response;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class RefundManagerConfig implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        public readonly bool $enabled,
        public readonly bool $autoStockReset,
        public readonly bool $verifyRefund,
        public readonly bool $showInstructions,
    ) {
    }
}
