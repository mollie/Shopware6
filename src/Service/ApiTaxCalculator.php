<?php declare(strict_types=1);


namespace Kiener\MolliePayments\Service;


class ApiTaxCalculator
{
    public function calculateTaxAmount(float $totalAmount, float $vatRate): float
    {
        return round($totalAmount, 2) * ($vatRate / (100 + $vatRate));
    }
}
