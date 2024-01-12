<?php

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

    /**
     * @param string $shopwareId
     * @param int $quantity
     */
    public function __construct(string $shopwareId, int $quantity)
    {
        $this->shopwareId = $shopwareId;
        $this->quantity = $quantity;
    }

    /**
     * @return string
     */
    public function getShopwareId(): string
    {
        return $this->shopwareId;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
