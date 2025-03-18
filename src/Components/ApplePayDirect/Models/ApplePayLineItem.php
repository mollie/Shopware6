<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Models;

class ApplePayLineItem
{
    /**
     * @var string
     */
    private $number;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $quantity;

    /**
     * @var float
     */
    private $price;

    public function __construct(string $number, string $name, int $quantity, float $price)
    {
        $this->number = $number;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->price = $price;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getPrice(): float
    {
        return $this->price;
    }
}
