<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Order\Admin\Response;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final readonly class ShippingData implements \JsonSerializable
{
    use JsonSerializableTrait;

    /**
     * @param array<string, ShippingStatusEntry> $status
     */
    public function __construct(
        public ShippingTotal $total,
        public array $status,
    ) {
    }
}
