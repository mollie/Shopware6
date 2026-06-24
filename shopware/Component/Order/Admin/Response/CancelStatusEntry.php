<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin\Response;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final readonly class CancelStatusEntry implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        public string $mollieOrderId,
        public string $mollieId,
        public bool $isCancelable,
        public int $cancelableQuantity,
        public int $quantityCanceled,
    ) {
    }
}
