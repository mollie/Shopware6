export interface RefundOrderLineItem {
    shopware: {
        id: string;
        label: string;
    };
    refundQuantity: number;
    refundAmount: number;
    resetStock: number;
}

export interface RefundItemPayload {
    id: string;
    label: string;
    quantity: number;
    amount: number;
    resetStock: number;
}

export interface RefundResponse {
    id?: string;
    success?: boolean;
    errors?: unknown[];
}

/**
 * Builds the request payload for a refund and interprets the response.
 * Kept free of any Shopware/Vue dependency so it can be unit tested in isolation.
 */
export default class RefundPayloadBuilder {
    /**
     * Maps the order line items of the cart form to the
     * item structure expected by the refund API.
     */
    buildItems(items: RefundOrderLineItem[]): RefundItemPayload[] {
        return items.map((item) => ({
            id: item.shopware.id,
            label: item.shopware.label,
            quantity: item.refundQuantity,
            amount: item.refundAmount,
            resetStock: item.resetStock,
        }));
    }

    /**
     * Gets if the provided refund response represents a success.
     * A success is either a created refund (string id) or an
     * explicit success flag.
     */
    isRefundSuccess(response: RefundResponse): boolean {
        return typeof response?.id === 'string' || response?.success === true;
    }
}
