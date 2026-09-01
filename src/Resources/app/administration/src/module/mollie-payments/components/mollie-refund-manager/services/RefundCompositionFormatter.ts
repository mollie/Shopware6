export interface CompositionEntry {
    label: string;
    swReference: string;
    quantity: number;
    amount: number;
}

export interface RefundWithComposition {
    metadata?: {
        composition?: CompositionEntry[];
    };
}

/**
 * Formats the composition of a Mollie refund into human readable labels.
 * Kept free of any Shopware/Vue dependency so it can be unit tested in isolation.
 */
export default class RefundCompositionFormatter {
    /**
     * Builds a list of display labels for the composition of the provided refund.
     * Falls back to the provided "no composition" label if the refund has none.
     *
     * @param refund            the refund containing the composition metadata
     * @param currencySymbol    the symbol used for the amount, e.g. "€"
     * @param noCompositionLabel the label to use if no composition exists
     */
    format(refund: RefundWithComposition, currencySymbol: string, noCompositionLabel: string): string[] {
        const composition = refund?.metadata?.composition;

        if (!composition || composition.length <= 0) {
            return [noCompositionLabel];
        }

        return composition.map((entry) => {
            const label = entry.swReference.length > 0 ? entry.swReference : entry.label;

            // we also allow line-item specific refunds with qty 0.
            // in this case, we should not display it to avoid mathematical confusion.
            if (entry.quantity > 0) {
                return `${label} (${entry.quantity} x ${entry.amount} ${currencySymbol})`;
            }

            return `${label} (${entry.amount} ${currencySymbol})`;
        });
    }
}
