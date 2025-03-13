<?php
declare(strict_types=1);

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

    public function __construct(string $mollieItemId, int $quantity)
    {
        $this->mollieItemId = $mollieItemId;
        $this->quantity = $quantity;
    }

    public function getMollieItemId(): string
    {
        return $this->mollieItemId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
