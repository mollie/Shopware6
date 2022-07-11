export default class ProductAttributes {

    /**
     *
     * @param productEntity
     */
    constructor(productEntity) {

        this._voucherType = '';
        this._subscriptionProduct = '';
        this._subscriptionInterval = '';
        this._subscriptionIntervalUnit = '';
        this._subscriptionRepetition = '';


        if (productEntity === null) {
            return;
        }

        const customFields = productEntity.customFields;

        if (customFields === null || customFields === undefined) {
            return;
        }

        this._voucherType = customFields['mollie_payments_product_voucher_type'];

        this._subscriptionProduct = customFields['mollie_payments_product_subscription_enabled'];
        this._subscriptionInterval = customFields['mollie_payments_product_subscription_interval'];
        this._subscriptionIntervalUnit = customFields['mollie_payments_product_subscription_interval_unit'];
        this._subscriptionRepetition = customFields['mollie_payments_product_subscription_repetition'];
    }


    /**
     *
     * @returns {*}
     */
    getVoucherType() {

        const stringType = this._voucherType + '';

        // we only allow values 1, 2, and 3
        // all other values are just empty
        if (stringType !== '0' && stringType !== '1' && stringType !== '2' && stringType !== '3') {
            return '';
        }

        return stringType;
    }

    /**
     * @returns {*}
     */
    isSubscriptionProduct() {

        const boolType = this._subscriptionProduct;

        if (!boolType) {
            return false;
        }

        return boolType;
    }

    /**
     * @returns {*}
     */
    getSubscriptionInterval() {
        return this._subscriptionInterval;
    }

    /**
     * @returns {*}
     */
    getSubscriptionIntervalUnit() {

        const stringType = this._subscriptionIntervalUnit + '';

        if (stringType !== 'days' && stringType !== 'weeks' && stringType !== 'months') {
            return '';
        }

        return stringType;
    }

    /**
     * @returns {*}
     */
    getSubscriptionRepetition() {
        return this._subscriptionRepetition;
    }


    /**
     *
     * @param originalFields
     * @returns {*}
     */
    toArray(originalFields) {
        return originalFields;
    }

    /**
     *
     * @returns {boolean}
     */
    hasData() {

        if (this._voucherType !== '') {
            return true;
        }

        if (this._subscriptionProduct) {
            return true;
        }

        return false;
    }

}
