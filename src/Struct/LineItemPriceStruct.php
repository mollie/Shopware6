<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct;

class LineItemPriceStruct
{
    /**
     * @var float
     */
    private $unitPrice;

    /**
     * @var float
     */
    private $totalAmount;

    /**
     * @var float
     */
    private $vatAmount;

    /**
     * @var float
     */
    private $vatRate;

    /**
     * @var float
     */
    private $roundingRest;

    public function __construct(float $unitPrice, float $totalAmount, float $vatAmount, float $vatRate, float $roundingRest = 0.0)
    {
        $this->unitPrice = $unitPrice;
        $this->totalAmount = $totalAmount;
        $this->vatAmount = $vatAmount;
        $this->vatRate = $vatRate;
        $this->roundingRest = $roundingRest;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function getVatAmount(): float
    {
        return $this->vatAmount;
    }

    public function getVatRate(): float
    {
        return $this->vatRate;
    }

    public function getRoundingRest(): float
    {
        return $this->roundingRest;
    }
}
