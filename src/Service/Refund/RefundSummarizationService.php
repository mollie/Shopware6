<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Refund;

/**
 * Service to handle the calculation of refund amounts from line items.
 * This class provides functionality to sum up the refund amounts for a collection of line items.
 */
class RefundSummarizationService
{
    /**
     * Calculates the total refund sum for a given list of line items.
     *
     * This method accepts an array of items, each containing an 'amount' field, and calculates the total refund amount
     * by summing the values of these fields. The 'amount' field is expected to be convertible to a float, and this method
     * will treat all values as floating-point numbers.
     *
     * @param array<int|string, mixed> $items Array of items, each containing an 'amount' field.
     *                                        The 'amount' field should be convertible to a float.
     * @return float Total refund sum calculated from the 'amount' values in the provided items.
     */
    public function getLineItemsRefundSum(array $items): float
    {
        // Extracts the 'amount' values from each item in the array
        $amounts = array_column($items, 'amount');

        // Converts each extracted amount to a float to ensure accurate summation
        $amounts = array_map('floatval', $amounts);

        // Sums up all converted amounts and returns the result as the total refund amount
        return array_sum($amounts);
    }
}
