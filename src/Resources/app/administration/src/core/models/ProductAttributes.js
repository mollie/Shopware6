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

        this._hasMollieModifiedData = false;

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


        // the next thing is, we want to have some basic information if
        // our mollie data is actually existing in the initial custom fields.
        // if we do not have it, then also don't create it later on.
        const keyList = [
            'mollie_payments_product_voucher_type',
            'mollie_payments_product_subscription_enabled',
            'mollie_payments_product_subscription_interval',
            'mollie_payments_product_subscription_interval_unit',
            'mollie_payments_product_subscription_repetition',
        ];

        this._hasMollieData = (this._checkMollieData(keyList, customFields));
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
        this._hasMollieModifiedData = true;
        this._voucherType = value;
    }

    /**
     * @param value
     */
    setSubscriptionProduct(value) {
        this._hasMollieModifiedData = true;
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
        this._hasMollieModifiedData = true;
        this._subscriptionInterval = value;
    }

    /**
     * @param value
     */
    setSubscriptionIntervalUnit(value) {
        this._hasMollieModifiedData = true;
        this._subscriptionIntervalUnit = value;
    }

    /**
     * @param value
     */
    setSubscriptionRepetition(value) {
        this._hasMollieModifiedData = true;
        this._subscriptionRepetition = value;
    }

    /**
     *
     */
    clearVoucherType() {
        this._hasMollieModifiedData = true;
        this._voucherType = '';
    }

    /**
     *
     */
    clearSubscriptionInterval() {
        this._hasMollieModifiedData = true;
        this._subscriptionInterval = '';
    }

    /**
     *
     */
    clearSubscriptionIntervalUnit() {
        this._hasMollieModifiedData = true;
        this._subscriptionIntervalUnit = '';
    }

    /**
     *
     */
    clearSubscriptionRepetition() {
        this._hasMollieModifiedData = true;
        this._subscriptionRepetition = '';
    }


    /**
     *
     * @param originalFields
     * @returns {*}
     */
    toArray(originalFields) {

        if (!this._hasMollieModifiedData) {
            return originalFields;
        }


        if (this._voucherType !== '') {
            originalFields['mollie_payments_product_voucher_type'] = String(this._voucherType);
        } else {
            originalFields['mollie_payments_product_voucher_type'] = null;
        }

        if (this._subscriptionProduct) {
            originalFields['mollie_payments_product_subscription_enabled'] = this._subscriptionProduct;
        } else {
            originalFields['mollie_payments_product_subscription_enabled'] = null;
        }

        if (this._subscriptionInterval !== undefined && this._subscriptionInterval !== '') {
            originalFields['mollie_payments_product_subscription_interval'] = parseInt(this._subscriptionInterval);
        } else {
            originalFields['mollie_payments_product_subscription_interval'] = null;
        }

        if (this._subscriptionIntervalUnit !== undefined && this._subscriptionIntervalUnit !== '') {
            originalFields['mollie_payments_product_subscription_interval_unit'] = this._subscriptionIntervalUnit;
        } else {
            originalFields['mollie_payments_product_subscription_interval_unit'] = null;
        }

        if (this._subscriptionRepetition !== undefined && this._subscriptionRepetition !== '') {
            originalFields['mollie_payments_product_subscription_repetition'] = this._subscriptionRepetition;
        } else {
            originalFields['mollie_payments_product_subscription_repetition'] = null;
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

    /**
     *
     * @param keyList
     * @param customFields
     * @returns {boolean}
     * @private
     */
    _checkMollieData(keyList, customFields) {
        var key = '';
        for (key in keyList) {
            if (key in customFields) {
                return true;
            }
        }
        return false;
    }

}
