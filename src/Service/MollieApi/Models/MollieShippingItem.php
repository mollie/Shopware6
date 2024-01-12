<?php

namespace Kiener\MolliePayments\Service\MollieApi\Models;

class MollieShippingItem
{

    /**
     * @var string
     */
    private $mollieItemId;

    /**
     * @var int
     */
    private $quantity;

    /**
     * @param string $mollieItemId
     * @param int $quantity
     */
    public function __construct(string $mollieItemId, int $quantity)
    {
        $this->mollieItemId = $mollieItemId;
        $this->quantity = $quantity;
    }

    /**
     * @return string
     */
    public function getMollieItemId(): string
    {
        return $this->mollieItemId;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
