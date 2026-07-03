<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Struct;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

final class CartItemStruct extends Struct
{
    use JsonSerializableTrait;

    private int $refunded = 0;

    public function __construct(
        private CartItemShopwareStruct $shopware,
    ) {
    }

    public function getRefunded(): int
    {
        return $this->refunded;
    }

    public function setRefunded(int $refunded): void
    {
        $this->refunded = $refunded;
    }

    public function getShopware(): CartItemShopwareStruct
    {
        return $this->shopware;
    }
}
