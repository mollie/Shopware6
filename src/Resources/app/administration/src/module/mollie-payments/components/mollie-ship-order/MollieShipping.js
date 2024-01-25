import OrderAttributes from '../../../../core/models/OrderAttributes';

export default class MollieShipping {


    /**
     *
     * @param shippingService
     */
    constructor(shippingService) {
        this._shippingService = shippingService;
    }

    /**
     *
     * @param order
     * @returns {boolean}
     */
    async isShippingPossible(order) {

        const orderAttributes = new OrderAttributes(order);

        // this can happen on subscription renewals...they have no order id
        // and therefore the order cannot be shipped
        if (orderAttributes.getOrderId() === '') {
            return false;
        }

        const items = await this.getShippableItems(order);

        for (let i = 0; i < items.length; i++) {
            const lineItem = items[i];

            if (lineItem.quantity > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     *
     * @param order
     * @returns {Promise<{quantity: *, label: *}[]>}
     */
    async getShippableItems(order) {
        // load the already shipped items
        // so that we can calculate what is left to be shipped
        await this._shippingService
            .status({
                orderId: order.id,
            })
            .then((response) => {
                this.shippedLineItems = response;
            });

        const finalItems = [];

        for (let i = 0; i < order.lineItems.length; i++) {
            const lineItem = order.lineItems[i];

            finalItems.push({
                id: lineItem.id,
                mollieId: lineItem.mollieOrderLineId,
                label: lineItem.label,
                quantity: this._shippableQuantity(lineItem),
            });
        }

        return finalItems;
    }

    /**
     *
     * @param item
     * @returns {*|number}
     * @private
     */
    _shippableQuantity(item) {

        if (this.shippedLineItems === null || this.shippedLineItems === undefined) {
            return 0;
        }

        const itemShippingStatus = this.shippedLineItems[item.id];

        if (itemShippingStatus === null || itemShippingStatus === undefined) {
            return 0;
        }

        return itemShippingStatus.quantityShippable;
    }

}
