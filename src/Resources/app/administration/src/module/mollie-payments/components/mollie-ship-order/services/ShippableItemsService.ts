export interface ShippingStatusEntry {
    mollieId?: string;
    shippableQuantity?: number;
}

export type ShippingStatusMap = Record<string, ShippingStatusEntry> | null;

export interface OrderLineItem {
    id: string;
    label: string;
}

export interface ShippableLineItem {
    id: string;
    mollieId: string | null;
    label: string;
    quantity: number;
    originalQuantity: number;
    selected: boolean;
}

export interface ShippingItemPayload {
    id: string;
    quantity: number;
}

/**
 * Builds the shippable line item rows for the ship-order modal and collects the
 * user's selection into the request payload. Kept free of any Shopware/Vue
 * dependency so it can be unit tested in isolation.
 */
export default class ShippableItemsService {
    buildShippableLineItems(lineItems: OrderLineItem[], shippingStatus: ShippingStatusMap): ShippableLineItem[] {
        return lineItems.map((lineItem) => {
            const status = shippingStatus?.[lineItem.id];
            const shippableQty = status?.shippableQuantity ?? 0;

            return {
                id: lineItem.id,
                mollieId: status?.mollieId ?? null,
                label: lineItem.label,
                quantity: shippableQty,
                originalQuantity: shippableQty,
                selected: false,
            };
        });
    }

    collectSelectedItems(items: ShippableLineItem[]): ShippingItemPayload[] {
        return items.filter((item) => item.selected).map((item) => ({ id: item.id, quantity: item.quantity }));
    }
}
