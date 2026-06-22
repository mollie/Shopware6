export interface CancelableItem {
    shopwareItemId: string;
}

export interface CancelRequest {
    shopwareLineId: string;
    quantity: number;
    resetStock: boolean;
}

export interface CancelResponse {
    success?: boolean;
    message?: string;
}

/**
 * Builds the request payload for cancelling an order line item and interprets
 * the response. Kept free of any Shopware/Vue dependency so it can be unit tested.
 */
export default class CancelItemService {
    /**
     * Maps the cart item and the user input to the structure expected by the
     * item cancel API.
     */
    buildCancelRequest(item: CancelableItem, quantity: number, resetStock: boolean): CancelRequest {
        return {
            shopwareLineId: item.shopwareItemId,
            quantity,
            resetStock,
        };
    }

    /**
     * Gets if the provided cancel response represents a success.
     */
    isCancelSuccess(response: CancelResponse): boolean {
        return response?.success === true;
    }

    /**
     * Builds the snippet key for the failure notification based on the response.
     */
    getFailureSnippetKey(response: CancelResponse): string {
        return `mollie-payments.modals.cancel.item.failed.${response?.message}`;
    }
}
