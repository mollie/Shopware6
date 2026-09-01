export interface RefundableItem {
    refundAmount: number | string;
}

export interface RefundLimitItem {
    refundedAmount?: number;
    shopware: {
        totalPrice: number;
        tax?: {
            totalItemTax: number;
        };
    };
}

// Refunded amounts come from Mollie rounded to cents, so an exactly-exhausted line
// can end up a fraction below its maximum. Half a cent of tolerance closes that gap.
const FULLY_REFUNDED_TOLERANCE = 0.005;

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
     * Gets the maximum amount that can be refunded for the provided item.
     * For net orders the tax is added on top of the line total, since the
     * refund can also include the tax portion.
     */
    getItemMaxRefundable(item: RefundLimitItem, isTaxStatusGross: boolean): number {
        let max = item.shopware.totalPrice;

        if (!isTaxStatusGross && item.shopware.tax) {
            max += item.shopware.tax.totalItemTax;
        }

        return this.roundToTwo(max);
    }

    /**
     * Gets the amount that can still be refunded for the provided item,
     * i.e. its maximum minus the already refunded amount.
     */
    getItemRemainingRefundable(item: RefundLimitItem, isTaxStatusGross: boolean): number {
        const remaining = this.getItemMaxRefundable(item, isTaxStatusGross) - (item.refundedAmount ?? 0);

        return remaining > 0 ? this.roundToTwo(remaining) : 0;
    }

    /**
     * Gets if the whole refundable amount of the line item has been refunded.
     * Quantity is not a reliable signal, since a partial-amount refund of a
     * single unit already counts as one refunded quantity.
     */
    isItemFullyRefunded(item: RefundLimitItem, isTaxStatusGross: boolean): boolean {
        const refundedAmount = item.refundedAmount ?? 0;

        return (
            refundedAmount > 0 &&
            refundedAmount + FULLY_REFUNDED_TOLERANCE >= this.getItemMaxRefundable(item, isTaxStatusGross)
        );
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
