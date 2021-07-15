<?php declare(strict_types=1);

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

    public function __construct(float $unitPrice, float $totalAmount, float $vatAmount, float $vatRate)
    {
        $this->unitPrice = $unitPrice;
        $this->totalAmount = $totalAmount;
        $this->vatAmount = $vatAmount;
        $this->vatRate = $vatRate;
    }

    /**
     * @return float
     */
    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    /**
     * @return float
     */
    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    /**
     * @return float
     */
    public function getVatAmount(): float
    {
        return $this->vatAmount;
    }

    /**
     * @return float
     */
    public function getVatRate(): float
    {
        return $this->vatRate;
    }
}
