import CreditcardAttributes from './CreditcardAttributes';

export default class OrderAttributes {
    /**
     *
     * @param orderEntity
     */
    constructor(orderEntity) {
        this._orderId = '';
        this._paymentId = '';
        this._swSubscriptionId = '';
        this._creditCardAttributes = null;
        this._paymentRef = null;

        if (orderEntity === null) {
            return;
        }

        this.customFields = orderEntity.customFields;

        if (this.customFields === null || this.customFields === undefined) {
            return;
        }

        if (this.customFields.mollie_payments === undefined || this.customFields.mollie_payments === null) {
            return;
        }

        const mollieData = this.customFields.mollie_payments;

        this._orderId = this._convertString(mollieData['order_id']);
        this._paymentId = this._convertString(mollieData['payment_id']);
        this._swSubscriptionId = this._convertString(mollieData['swSubscriptionId']);
        this._paymentRef = this._convertString(mollieData['third_party_payment_id']);
        this._creditCardAttributes = new CreditcardAttributes(mollieData);
    }

    /**
     *
     * @returns {boolean}
     */
    isMollieOrder() {
        return this.customFields !== null && 'mollie_payments' in this.customFields;
    }

    /**
     *
     * @returns {null|CreditcardAttributes|*}
     */
    getCreditCardAttributes() {
        return this._creditCardAttributes;
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
     * @returns {string|*|null}
     */
    getMollieID() {
        if (this.getOrderId() !== '') {
            return this.getOrderId();
        }

        if (this.getPaymentId() !== '') {
            return this.getPaymentId();
        }

        return null;
    }

    /**
     *
     * @returns {boolean}
     */
    isSubscription() {
        return this.getSwSubscriptionId() !== '';
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
     * @returns {string}
     */
    getPaymentRef() {
        return this._paymentRef;
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
