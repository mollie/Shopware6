<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin\Response;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class ShippingTotal implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        public readonly string $amount,
        public readonly int $quantity,
        public readonly int $shippable,
    ) {
    }
}
