<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ShipmentManager\Models;

class ShipmentLineItem
{
    /**
     * @var string
     */
    private $shopwareId;

    /**
     * @var int
     */
    private $quantity;

    public function __construct(string $shopwareId, int $quantity)
    {
        $this->shopwareId = $shopwareId;
        $this->quantity = $quantity;
    }

    public function getShopwareId(): string
    {
        return $this->shopwareId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
