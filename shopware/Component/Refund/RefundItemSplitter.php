<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund;

use Mollie\Shopware\Mollie;

/**
 * Splits a requested refund amount for a single line item into full units, a partial
 * remainder and an excess that goes beyond the line item maximum. This keeps the stored
 * refund composition faithful to the real per-unit breakdown instead of booking the whole
 * amount as a single entry.
 */
final class RefundItemSplitter
{
    private const TOLERANCE = 0.005;

    /**
     * @return array{fullUnits: int, unitPrice: float, remainder: float, excess: float}
     */
    public function split(float $requestedAmount, float $lineMax, int $quantity, float $alreadyRefunded): array
    {
        $requestedAmount = round($requestedAmount, Mollie::ROUNDING_PRECISION);
        // Guard the divisor so quantity 0 can never cause a division by zero (max(1, ...)).
        // Do NOT round the unit price: a rounded price (e.g. 1.499 -> 1.50) would make
        // floor(lineAmount / unitPrice) report one unit too few for a full-quantity refund.
        $unitPrice = $lineMax / max(1, $quantity);

        $lineRemaining = max(0.0, round($lineMax - $alreadyRefunded, Mollie::ROUNDING_PRECISION));
        $lineAmount = min($requestedAmount, $lineRemaining);
        $excess = round($requestedAmount - $lineAmount, Mollie::ROUNDING_PRECISION);

        $fullUnits = 0;
        $remainder = 0.0;

        if ($lineAmount > self::TOLERANCE && $unitPrice > 0.0) {
            $unitsAlreadyRefunded = (int) floor(($alreadyRefunded + self::TOLERANCE) / $unitPrice);
            $unitsRemaining = max(0, $quantity - $unitsAlreadyRefunded);
            $fullUnits = min((int) floor(($lineAmount + self::TOLERANCE) / $unitPrice), $unitsRemaining);
            $remainder = round($lineAmount - ($fullUnits * $unitPrice), Mollie::ROUNDING_PRECISION);
        } elseif ($lineAmount > self::TOLERANCE) {
            // no usable unit price (e.g. delivery) -> everything is a single partial entry
            $remainder = $lineAmount;
        }

        return [
            'fullUnits' => $fullUnits,
            'unitPrice' => $unitPrice,
            'remainder' => $remainder,
            'excess' => $excess,
        ];
    }
}
