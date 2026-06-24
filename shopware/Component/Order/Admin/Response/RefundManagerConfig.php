<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin\Response;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final readonly class RefundManagerConfig implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        public bool $enabled,
        public bool $autoStockReset,
        public bool $verifyRefund,
        public bool $showInstructions,
    ) {
    }
}
