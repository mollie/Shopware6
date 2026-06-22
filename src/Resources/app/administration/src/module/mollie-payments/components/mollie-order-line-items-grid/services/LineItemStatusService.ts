export interface ShippingItemStatus {
    shippableQuantity: number;
    quantityShipped: number;
}

export interface CancelItemStatus {
    mollieId?: string;
    cancelableQuantity?: number;
    quantityCanceled?: number;
    isCancelable?: boolean;

    [key: string]: any;
}

export type ShippingStatusMap = Record<string, ShippingItemStatus> | null;
export type CancelStatusMap = Record<string, CancelItemStatus> | null;

export interface CancelResponse {
    success?: boolean;
    data?: {
        id: string;
        quantity?: number;
    };
}

export interface LineItem {
    id: string;
    label?: string;
    payload?: unknown;
}

// Placeholder shown while the status for an item has not been loaded yet.
const UNKNOWN = '~';

/**
 * Reads and updates the Mollie shipping/cancel status maps for order line items.
 * Kept free of any Shopware/Vue dependency so it can be unit tested in isolation.
 */
export default class LineItemStatusService {
    shippableQuantity(shippingStatus: ShippingStatusMap, itemId: string): number | string {
        return shippingStatus?.[itemId]?.shippableQuantity ?? UNKNOWN;
    }

    shippedQuantity(shippingStatus: ShippingStatusMap, itemId: string): number | string {
        return shippingStatus?.[itemId]?.quantityShipped ?? UNKNOWN;
    }

    canceledQuantity(cancelStatus: CancelStatusMap, itemId: string): number | string {
        return cancelStatus?.[itemId]?.quantityCanceled ?? UNKNOWN;
    }

    isCancelable(cancelStatus: CancelStatusMap, itemId: string): boolean {
        return cancelStatus?.[itemId]?.isCancelable ?? false;
    }

    /**
     * Returns a new cancel status map that reflects the given cancel response.
     * The matching Mollie line gets its canceled/cancelable quantities updated;
     * an invalid response leaves the map unchanged.
     */
    applyCancelResponse(cancelStatus: CancelStatusMap, response: CancelResponse): CancelStatusMap {
        if (!response || !response.success || !response.data) {
            return cancelStatus;
        }

        const cancelledMollieId = response.data.id;
        const cancelledQuantity = response.data.quantity || 0;

        const updated: Record<string, CancelItemStatus> = {};
        Object.entries(cancelStatus || {}).forEach(([swItemId, status]) => {
            if (status.mollieId !== cancelledMollieId) {
                updated[swItemId] = status;
                return;
            }

            const newCancelableQty = Math.max(0, (status.cancelableQuantity || 0) - cancelledQuantity);
            updated[swItemId] = {
                ...status,
                quantityCanceled: (status.quantityCanceled || 0) + cancelledQuantity,
                cancelableQuantity: newCancelableQty,
                isCancelable: newCancelableQty > 0,
            };
        });

        return updated;
    }

    /**
     * Builds the data passed to the cancel-item modal: the stored status of the
     * item merged with the identifying fields. Returns an empty object if the
     * item has no cancel status.
     */
    buildCancelData(cancelStatus: CancelStatusMap, item: LineItem): CancelItemStatus | Record<string, never> {
        const status = cancelStatus?.[item.id];
        if (!status) {
            return {};
        }

        return {
            ...status,
            shopwareItemId: item.id,
            label: item.label,
            payload: item.payload,
        };
    }
}
