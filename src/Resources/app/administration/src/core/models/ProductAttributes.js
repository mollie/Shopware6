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

        this._voucherType = customFields['mollie_payments.product.voucher_type'];

        this._subscriptionProduct = customFields['mollie_payments.product.subscription.enabled'];
        this._subscriptionInterval = customFields['mollie_payments.product.subscription.interval'];
        this._subscriptionIntervalUnit = customFields['mollie_payments.product.subscription.interval_unit'];
        this._subscriptionRepetition = customFields['mollie_payments.product.subscription.repetition'];
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
     * @param value
     */
    setVoucherType(value) {
        this._voucherType = value;
    }

    /**
     * @param value
     */
    setSubscriptionProduct(value) {
        if (typeof value === 'boolean') {
            this._subscriptionProduct = value;
        } else {
            this._subscriptionProduct = false;
        }
    }

    /**
     * @param value
     */
    setSubscriptionInterval(value) {
        if (typeof value === 'number') {
            this._subscriptionInterval = value;
        } else {
            this._subscriptionInterval = '';
        }
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
        if (typeof value === 'number') {
            this._subscriptionRepetition = value;
        } else {
            this._subscriptionRepetition = '';
        }
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
     * @param originalFields
     * @returns {*}
     */
    toArray(originalFields) {

        if (this._voucherType !== '') {
            originalFields['mollie_payments.product.voucher_type'] = String(this._voucherType);
        } else {
            originalFields = this._removeKey(originalFields, 'mollie_payments.product.voucher_type');
        }

        if (this._subscriptionProduct) {
            originalFields['mollie_payments.product.subscription.enabled'] = this._subscriptionProduct;
        } else {
            originalFields = this._removeKey(originalFields, 'mollie_payments.product.subscription.enabled');
        }

        if (this._subscriptionInterval !== undefined && this._subscriptionInterval !== '') {
            originalFields['mollie_payments.product.subscription.interval'] = parseInt(this._subscriptionInterval);
        } else {
            originalFields = this._removeKey(originalFields, 'mollie_payments.product.subscription.interval');
        }

        if (this._subscriptionIntervalUnit !== undefined && this._subscriptionIntervalUnit !== '') {
            originalFields['mollie_payments.product.subscription.interval_unit'] = this._subscriptionIntervalUnit;
        } else {
            originalFields = this._removeKey(originalFields, 'mollie_payments.product.subscription.interval_unit');
        }

        if (this._subscriptionRepetition !== undefined && this._subscriptionRepetition !== '') {
            originalFields['mollie_payments.product.subscription.repetition'] = parseInt(this._subscriptionRepetition);
        } else {
            originalFields = this._removeKey(originalFields, 'mollie_payments.product.subscription.repetition');
        }

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

    /**
     *
     * @param arrayName
     * @param key
     * @returns {any[]}
     * @private
     */
    _removeKey(arrayName, key) {
        var x;
        var tmpArray = {};
        for (x in arrayName) {
            if (x !== key) {
                tmpArray[x] = arrayName[x];
            }
        }
        return tmpArray;
    }

}
