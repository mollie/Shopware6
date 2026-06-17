<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin\Response;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final readonly class ShippingStatusEntry implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        public string $mollieOrderId,
        public string $mollieId,
        public bool $isShippable,
        public int $shippableQuantity,
        public int $quantityShipped,
    ) {
    }
}
