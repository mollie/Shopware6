export interface RefundableItem {
    refundAmount: number | string;
}

/**
 * Pure calculation helpers for the refund manager summary.
 * Kept free of any Shopware/Vue dependency so it can be unit tested in isolation.
 */
export default class RefundCalculator {
    /**
     * Sums up the refund amount of all provided line items
     * and returns the total, rounded to two decimals.
     */
    calculateTotalRefundAmount(items: RefundableItem[]): number {
        const total = items.reduce((sum, item) => sum + parseFloat(String(item.refundAmount)), 0);

        return this.roundToTwo(total);
    }

    /**
     * Rounds the provided value to two decimals while avoiding
     * the typical floating point issues of Math.round.
     */
    roundToTwo(value: number): number {
        return Number(`${Math.round(Number(`${value}e+2`))}e-2`);
    }

    /**
     * Gets if the "fix difference" button should be available.
     * This is only the case if the refund amount and the remaining
     * amount differ slightly (rounding issues), but are not identical.
     */
    isFixDiffAvailable(refundAmount: number, remainingAmount: number): boolean {
        const diff = Math.abs(refundAmount - remainingAmount);

        // show if 7 cents or less diff
        return diff > 0 && diff <= 0.07;
    }
}
