export type RefundMode = 'none' | 'quantity' | 'amount';

export interface RefundItemShopware {
    quantity: number;
    unitPrice: number;
    isPromotion: boolean;
    isDelivery: boolean;
    promotion: {
        discount: number;
        quantity?: number;
        taxValue?: number;
    };
    tax?: {
        perItemTax: number;
        totalToPerItemRoundingDiff: number;
    };
}

export interface RefundItem {
    shopware: RefundItemShopware;
    refunded: number;
    refundMode?: RefundMode;
    refundQuantity?: number;
    refundAmount?: number;
    resetStock?: number;
    refundPromotion?: boolean;
    refundTax?: boolean;
}

const REFUND_MODE_NONE: RefundMode = 'none';
const REFUND_MODE_QTY: RefundMode = 'quantity';
const REFUND_MODE_AMOUNT: RefundMode = 'amount';

export default class RefundItemService {
    // -------------------------------------------------------------------------------------------------
    // GETTERS
    // -------------------------------------------------------------------------------------------------

    /**
     * Gets if the type of the provided item is a Shopware promotion line item.
     */
    isTypePromotion(item: RefundItem): boolean {
        return item.shopware.isPromotion;
    }

    /**
     * Gets if the type of the provided item is a Shopware delivery line item.
     */
    isTypeDelivery(item: RefundItem): boolean {
        return item.shopware.isDelivery;
    }

    /**
     * Gets if the provided item has been discounted by a Shopware promotion.
     */
    isDiscounted(item: RefundItem): boolean {
        return item.shopware.promotion.discount > 0;
    }

    /**
     * Gets if the provided item can still be refunded or if the maximum of
     * available refunds has been reached.
     */
    isRefundable(item: RefundItem): boolean {
        if (item.shopware.unitPrice === 0) {
            return false;
        }

        // we have the use case that a merchant refunds an item with qty 1
        // but with half the price.
        // the customer complains and the merchant refunds the rest.
        // the merchant wants a reference to the refunded item and tries to use qty 0, so that
        // it will appear in the composition.
        // Therefore isRefundable needs to be TRUE.
        return true;
    }

    // -------------------------------------------------------------------------------------------------
    // FUNCTIONS
    // -------------------------------------------------------------------------------------------------

    /**
     * Sets the stock quantity that should be used to increase the stock again.
     */
    setStockReset(item: RefundItem, value: number): void {
        // only do this, if not yet configured
        if ((item.resetStock ?? 0) > 0) {
            return;
        }

        item.resetStock = value;
    }

    /**
     * Prepares the item data to be fully refunded once the refund is triggered with this item.
     */
    setFullRefund(item: RefundItem): void {
        item.refundQuantity = item.shopware.quantity - item.refunded;

        this.onQuantityChanged(item);
    }

    /**
     * Resets the user input data and the item to have its initial values again.
     */
    resetRefundData(item: RefundItem): void {
        item.refundMode = REFUND_MODE_NONE;
        item.refundQuantity = 0;
        item.refundAmount = 0;
        item.resetStock = 0;
        item.refundPromotion = false;
        item.refundTax = false;
    }

    // -------------------------------------------------------------------------------------------------
    // EVENTS
    // -------------------------------------------------------------------------------------------------

    /**
     * Call this event if you have just changed the quantity to be refunded.
     * This will make sure to prepare everything along the quantity value.
     */
    onQuantityChanged(item: RefundItem): void {
        // do only update if our amount has not yet been adjusted
        if (item.refundMode === REFUND_MODE_AMOUNT) {
            return;
        }

        const maxQty = item.shopware.quantity - item.refunded;

        if ((item.refundQuantity ?? 0) > maxQty) {
            item.refundQuantity = maxQty;
        }

        item.refundMode = REFUND_MODE_QTY;

        this._calculateItemAmount(item);
    }

    /**
     * Call this event if you have just changed the amount to be refunded.
     * This will make sure to prepare everything along the amount value.
     */
    onAmountChanged(item: RefundItem): void {
        if (item.refundMode === REFUND_MODE_QTY) {
            return;
        }

        item.refundMode = REFUND_MODE_AMOUNT;

        if ((item.refundQuantity ?? 0) <= 0) {
            item.refundQuantity = parseInt(String((item.refundAmount ?? 0) / item.shopware.unitPrice), 10);
        }
    }

    /**
     * Call this event if you have just changed the refund-tax property.
     * This will make sure to prepare all data accordingly.
     */
    onRefundTaxChanged(item: RefundItem): void {
        // do nothing in "amount" mode because we have a custom amount here
        if (item.refundMode === REFUND_MODE_AMOUNT) {
            return;
        }

        this._calculateItemAmount(item);
    }

    /**
     * Call this event if you have just changed the promotion-reductions property.
     * This will make sure to prepare all data accordingly.
     */
    onPromotionDeductionChanged(item: RefundItem): void {
        // do nothing in "amount" mode because we have a custom amount here
        if (item.refundMode === REFUND_MODE_AMOUNT) {
            return;
        }

        this._calculateItemAmount(item);
    }

    /**
     * This recalculates the refund amount property depending on the current state of the item.
     */
    _calculateItemAmount(item: RefundItem): void {
        const refundQuantity = item.refundQuantity ?? 0;
        const newRefundAmount = item.shopware.unitPrice * refundQuantity;

        let refundTaxAmount = 0;
        if (item.refundTax && item.shopware.tax) {
            refundTaxAmount += item.shopware.tax.perItemTax * refundQuantity;

            if (refundQuantity > 0 && refundQuantity + item.refunded === item.shopware.quantity) {
                refundTaxAmount += item.shopware.tax.totalToPerItemRoundingDiff;
            }

            let promotionTaxValue = 0;
            if ((item.shopware.promotion.taxValue ?? 0) > 0) {
                const promotionTaxValuePerQty =
                    (item.shopware.promotion.taxValue ?? 0) / (item.shopware.promotion.quantity ?? 1);
                promotionTaxValue = promotionTaxValuePerQty * refundQuantity;
            }

            refundTaxAmount -= promotionTaxValue;
        }

        if (item.refundPromotion) {
            // we have to calculate the amount of a single item, because
            // the promotion discount is the full discount on all of these items.
            const discountPerQty = item.shopware.promotion.discount / (item.shopware.promotion.quantity ?? 1);
            const discount = refundQuantity * discountPerQty;

            item.refundAmount = newRefundAmount + refundTaxAmount - discount;
        } else {
            item.refundAmount = newRefundAmount + refundTaxAmount;
        }
    }
}
