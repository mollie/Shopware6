<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin\Response;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final readonly class ShippingTotal implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        public string $amount,
        public int $quantity,
        public int $shippable,
    ) {
    }
}
