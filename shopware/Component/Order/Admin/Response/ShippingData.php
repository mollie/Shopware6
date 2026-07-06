<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin\Response;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class ShippingData implements \JsonSerializable
{
    use JsonSerializableTrait;

    /**
     * @param array<string, ShippingStatusEntry> $status
     */
    public function __construct(
        public readonly ShippingTotal $total,
        public readonly array $status,
    ) {
    }
}
