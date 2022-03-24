const REFUND_MODE_NONE = 'none';
const REFUND_MODE_QTY = 'quantity';
const REFUND_MODE_AMOUNT = 'amount';


export default class RefundItemService {


    // ---------------------------------------------------------------------------------------------------------
    // <editor-fold desc="GETTERS">
    // ---------------------------------------------------------------------------------------------------------

    /**
     * Gets if the type of the provided item
     * is a Shopware promotion line item.
     * @param item
     * @returns {boolean}
     */
    isTypePromotion(item) {
        return item.shopware.isPromotion;
    }

    /**
     * Gets if the type of the provided item
     * is a Shopware delivery line item.
     * @param item
     * @returns {boolean}
     */
    isTypeDelivery(item) {
        return item.shopware.isDelivery;
    }

    /**
     * Gets if the provided item has been discounted
     * by a Shopware promotion.
     * @param item
     * @returns {boolean}
     */
    isDiscounted(item) {
        return item.shopware.promotion.discount > 0;
    }

    /**
     * Gets if the provided item can still be refunded
     * or if the maximum of available refunds has been reached.
     * @param item
     * @returns {boolean}
     */
    isRefundable(item) {

        if (item.shopware.unitPrice === 0) {
            return false;
        }

        return (item.refunded < item.shopware.quantity);
    }

    // ---------------------------------------------------------------------------------------------------------
    // </editor-fold>
    // ---------------------------------------------------------------------------------------------------------


    // ---------------------------------------------------------------------------------------------------------
    // <editor-fold desc="FUNCTIONS">
    // ---------------------------------------------------------------------------------------------------------

    /**
     * Sets the stock quantity that should be
     * used to increase the stock again
     * @param item
     * @param value
     */
    setStockReset(item, value) {

        // only do this, if not yet configured
        if(item.resetStock > 0) {
            return;
        }

        item.resetStock = value;
    }

    /**
     * Prepares the item data to be full refunded
     * once the refund is triggered with this item.
     * @param item
     */
    setFullRefund(item) {
        item.refundQuantity = item.shopware.quantity - item.refunded;

        this.onQuantityChanged(item);
    }

    /**
     * Resets the user input data and the item
     * to have its initial values again.
     * @param item
     */
    resetRefundData(item) {
        item.refundMode = REFUND_MODE_NONE;
        item.refundQuantity = 0;
        item.refundAmount = 0;
        item.resetStock = 0;
        item.refundPromotion = false;
    }

    // ---------------------------------------------------------------------------------------------------------
    // </editor-fold>
    // ---------------------------------------------------------------------------------------------------------


    // ---------------------------------------------------------------------------------------------------------
    // <editor-fold desc="EVENTS">
    // ---------------------------------------------------------------------------------------------------------

    /**
     * Call this event if you have just changed the quantity to be refunded.
     * This will make sure to prepare everything along the quantity value.
     * @param item
     */
    onQuantityChanged(item) {

        // do only update if our
        // amount has not yet been adjusted
        if (item.refundMode === REFUND_MODE_AMOUNT) {
            return;
        }

        const maxQty = item.shopware.quantity - item.refunded;

        if (item.refundQuantity > maxQty) {
            item.refundQuantity = maxQty;
        }

        item.refundMode = REFUND_MODE_QTY;

        this._calculateItemAmount(item);
    }

    /**
     * Call this event if you have just changed the amount to be refunded.
     * This will make sure to prepare everything along the amount value.
     * @param item
     */
    onAmountChanged(item) {
        if (item.refundMode === REFUND_MODE_QTY) {
            return;
        }

        item.refundMode = REFUND_MODE_AMOUNT;

        if (item.refundQuantity <= 0) {
            item.refundQuantity = parseInt(item.refundAmount / item.shopware.unitPrice);
        }
    }

    /**
     * Call this event if you have just changed the promotion-reductions property.
     * This will make sure to prepare all data accordingly.
     * @param item
     */
    onPromotionDeductionChanged(item) {

        // do nothing in "amount" mode
        // because we have a custom amount here
        if (item.refundMode === REFUND_MODE_AMOUNT) {
            return;
        }

        this._calculateItemAmount(item);
    }

    // ---------------------------------------------------------------------------------------------------------
    // </editor-fold>
    // ---------------------------------------------------------------------------------------------------------


    /**
     * This recalculates the refund amount property
     * depending on the current state of the item.
     * @param item
     */
    _calculateItemAmount(item) {

        const newRefundAmount = (item.shopware.unitPrice * item.refundQuantity);

        if (item.refundPromotion) {
            // we have to calculate the amount of a single item, because
            // the promotion discount is the full discount on all of these items.
            const discountPerQty = item.shopware.promotion.discount / item.shopware.promotion.quantity;
            const discount = (item.refundQuantity * discountPerQty);

            item.refundAmount = newRefundAmount - discount;

        } else {
            item.refundAmount = newRefundAmount;
        }
    }

}
