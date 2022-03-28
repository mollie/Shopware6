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
        this._subscriptionRepetitionType = '';


        if (productEntity === null) {
            return;
        }

        if (!productEntity.customFields) {
            return;
        }

        if (!productEntity.customFields.mollie_payments) {
            return;
        }

        const mollieFields = productEntity.customFields.mollie_payments;


        if (mollieFields === undefined) {
            return;
        }

        this._voucherType = mollieFields.voucher_type;

        this._subscriptionProduct = mollieFields.subscription_product;
        this._subscriptionInterval = mollieFields.subscription_interval;
        this._subscriptionIntervalUnit = mollieFields.subscription_interval_unit;
        this._subscriptionRepetition = mollieFields.subscription_repetition;
        this._subscriptionRepetitionType = mollieFields.subscription_repetition_type;
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
     * @returns {*}
     */
    getSubscriptionRepetitionType() {

        const stringType = this._subscriptionRepetitionType + '';

        if (stringType !== 'times' && stringType !== 'infinite') {
            return '';
        }

        return stringType;
    }

    /**
     *
     * @param value
     */
    setVoucherType(value) {
        this._voucherType = value;
    }

    /**
     * @param value
     */
    setSubscriptionProduct(value) {
        this._subscriptionProduct = value;
    }

    /**
     * @param value
     */
    setSubscriptionInterval(value) {
        this._subscriptionInterval = value;
    }

    /**
     * @param value
     */
    setSubscriptionIntervalUnit(value) {
        this._subscriptionIntervalUnit = value;
    }

    /**
     * @param value
     */
    setSubscriptionRepetition(value) {
        this._subscriptionRepetition = value;
    }

    /**
     * @param value
     */
    setSubscriptionRepetitionType(value) {
        this._subscriptionRepetitionType = value;
    }

    /**
     *
     */
    clearVoucherType() {
        this._voucherType = '';
    }

    /**
     *
     */
    clearSubscriptionInterval() {
        this._subscriptionInterval = '';
    }

    /**
     *
     */
    clearSubscriptionIntervalUnit() {
        this._subscriptionIntervalUnit = '';
    }

    /**
     *
     */
    clearSubscriptionRepetition() {
        this._subscriptionRepetition = '';
    }

    /**
     *
     */
    clearSubscriptionRepetitionType() {
        this._subscriptionRepetitionType = '';
    }

    /**
     *
     * @returns {string[]}
     */
    toArray() {
        const mollie = {};

        if (this._voucherType !== '') {
            mollie['voucher_type'] = this._voucherType;
        }

        if (this._subscriptionProduct) {
            mollie['subscription_product'] = this._subscriptionProduct;
        }
        if (this._subscriptionInterval !== '') {
            mollie['subscription_interval'] = this._subscriptionInterval;
        }
        if (this._subscriptionIntervalUnit !== '') {
            mollie['subscription_interval_unit'] = this._subscriptionIntervalUnit;
        }
        if (this._subscriptionRepetition !== '') {
            mollie['subscription_repetition'] = this._subscriptionRepetition;
        }
        if (this._subscriptionRepetitionType !== '') {
            mollie['subscription_repetition_type'] = this._subscriptionRepetitionType;
        }

        return mollie;
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
