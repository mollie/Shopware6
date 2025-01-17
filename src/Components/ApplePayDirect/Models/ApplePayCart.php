<?php

namespace Kiener\MolliePayments\Components\ApplePayDirect\Models;

class ApplePayCart
{
    /**
     * @var ApplePayLineItem[]
     */
    private $items;

    /**
     * @var ApplePayLineItem[]
     */
    private $shippings;

    /**
     * @var ?ApplePayLineItem
     */
    private $taxes;


    /**
     *
     */
    public function __construct()
    {
        $this->items = [];

        $this->shippings = [];
        $this->taxes = null;
    }

    /**
     * @return ApplePayLineItem[]
     */
    public function getShippings(): array
    {
        return $this->shippings;
    }

    /**
     * @return null|ApplePayLineItem
     */
    public function getTaxes(): ?ApplePayLineItem
    {
        return $this->taxes;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        $amount = $this->getProductAmount();
        $amount += $this->getShippingAmount();

        return $amount;
    }

    /**
     * @return float
     */
    public function getProductAmount(): float
    {
        $amount = 0;

        /** @var ApplePayLineItem $item */
        foreach ($this->items as $item) {
            $amount += ($item->getQuantity() * $item->getPrice());
        }

        return $amount;
    }

    /**
     * @return float
     */
    public function getShippingAmount(): float
    {
        $amount = 0;

        /** @var ApplePayLineItem $item */
        foreach ($this->shippings as $item) {
            $amount += ($item->getQuantity() * $item->getPrice());
        }

        return $amount;
    }

    /**
     * @return ApplePayLineItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param string $number
     * @param string $name
     * @param int $quantity
     * @param float $price
     */
    public function addItem(string $number, string $name, int $quantity, float $price): void
    {
        $this->items[] = new ApplePayLineItem($number, $name, $quantity, $price);
    }

    /**
     * @param string $name
     * @param float $price
     */
    public function addShipping(string $name, float $price): void
    {
        $this->shippings[] = new ApplePayLineItem("SHIPPING", $name, 1, $price);
    }

    /**
     * @param float $price
     */
    public function setTaxes(float $price): void
    {
        $this->taxes = new ApplePayLineItem("TAXES", '', 1, $price);
    }
}
