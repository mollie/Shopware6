<?php
declare(strict_types=1);

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

    private bool $isGrossPriceDisplay;

    public function __construct(bool $isGrossPriceDisplay)
    {
        $this->isGrossPriceDisplay = $isGrossPriceDisplay;

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

    public function getTaxes(): ?ApplePayLineItem
    {
        return $this->taxes;
    }

    public function getAmount(): float
    {
        $amount = $this->getProductAmount();
        $amount += $this->getShippingAmount();

        if (! $this->isGrossPriceDisplay && $this->getTaxes() instanceof ApplePayLineItem) {
            // our products show the NET price, so
            // we also have to add the taxes to the total amount
            $amount += $this->getTaxes()->getPrice();
        }

        return $amount;
    }

    public function getProductAmount(): float
    {
        $amount = 0;

        /** @var ApplePayLineItem $item */
        foreach ($this->items as $item) {
            $amount += ($item->getQuantity() * $item->getPrice());
        }

        return $amount;
    }

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

    public function addItem(string $number, string $name, int $quantity, float $price): void
    {
        $this->items[] = new ApplePayLineItem($number, $name, $quantity, $price);
    }

    public function addShipping(string $name, float $price): void
    {
        $this->shippings[] = new ApplePayLineItem('SHIPPING', $name, 1, $price);
    }

    public function setTaxes(float $price): void
    {
        $this->taxes = new ApplePayLineItem('TAXES', '', 1, $price);
    }
}
