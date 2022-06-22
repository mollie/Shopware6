export default class OrderAttributes {

    /**
     *
     * @param orderEntity
     */
    constructor(orderEntity) {

        this._orderId = '';
        this._paymentId = '';
        this._swSubscriptionId = '';

        if (orderEntity === null) {
            return;
        }

        const customFields = orderEntity.customFields;

        if (customFields === null || customFields === undefined) {
            return;
        }

        if (customFields.mollie_payments === undefined || customFields.mollie_payments === null) {
            return;
        }

        const mollieData = customFields.mollie_payments;

        this._orderId = this._convertString(mollieData['order_id']);
        this._paymentId = this._convertString(mollieData['payment_id']);
        this._swSubscriptionId = this._convertString(mollieData['swSubscriptionId']);
    }

    /**
     *
     * @returns {*}
     */
    getOrderId() {
        return this._orderId;
    }

    /**
     *
     * @returns {string|*}
     */
    getPaymentId() {
        return this._paymentId;
    }

    /**
     *
     * @returns {string|*}
     */
    getSwSubscriptionId() {
        return this._swSubscriptionId;
    }

    /**
     *
     * @param value
     * @returns {string}
     * @private
     */
    _convertString(value) {
        if (value === undefined || value === null) {
            return '';
        }

        return String(value);
    }

}
