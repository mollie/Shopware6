<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Refund\Item;

class RefundItem
{
    /**
     * @var ?string
     */
    private $shopwareLineID;

    /**
     * @var string
     */
    private $mollieLineID;

    /**
     * @var string
     */
    private $shopwareReference;

    /**
     * @var int
     */
    private $quantity;

    /**
     * @var float
     */
    private $amount;
    /**
     * @var null|string
     */
    private $shopwareLineVersionId;

    /**
     * @param string $shopwareLineID
     */
    public function __construct(string $mollieLineID, string $shopwareReference, int $quantity, float $amount, ?string $shopwareLineID, ?string $shopwareLineVersionId)
    {
        $this->shopwareLineID = $shopwareLineID;
        $this->mollieLineID = $mollieLineID;
        $this->shopwareReference = $shopwareReference;
        $this->quantity = $quantity;
        $this->amount = round($amount, 2);
        $this->shopwareLineVersionId = $shopwareLineVersionId;
    }

    /**
     * @return string
     */
    public function getShopwareLineID(): ?string
    {
        return $this->shopwareLineID;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getShopwareReference(): string
    {
        return $this->shopwareReference;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getMollieLineID(): string
    {
        return $this->mollieLineID;
    }

    public function getShopwareLineVersionId(): ?string
    {
        return $this->shopwareLineVersionId;
    }
}
