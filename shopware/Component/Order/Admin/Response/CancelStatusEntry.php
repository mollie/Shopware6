<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin\Response;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class CancelStatusEntry implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        public readonly string $mollieOrderId,
        public readonly string $mollieId,
        public readonly bool $isCancelable,
        public readonly int $cancelableQuantity,
        public readonly int $quantityCanceled,
    ) {
    }
}
