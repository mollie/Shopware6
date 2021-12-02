export default class ProductAttributes {

    /**
     *
     * @param productEntity
     */
    constructor(productEntity) {
        this._voucherType = '';
        this._mollieSubscriptionProduct = '';
        this._mollieSubscriptionIntervalAmount = '';
        this._mollieSubscriptionIntervalType = '';
        this._mollieSubscriptionRepetitionAmount = '';
        this._mollieSubscriptionRepetitionType = '';


        if (productEntity === null) {
            return;
        }

        if (!productEntity.customFields) {
            return;
        }

        if (!productEntity.customFields.mollie_payments && !productEntity.customFields.mollie_subscription) {
            return;
        }

        const mollieFields = productEntity.customFields.mollie_payments;
        if (mollieFields !== undefined) {
            this._voucherType = mollieFields.voucher_type;
        }

        const mollieFieldsSubscription = productEntity.customFields.mollie_subscription;
        if (mollieFieldsSubscription !== undefined) {
            this._mollieSubscriptionProduct = mollieFieldsSubscription.mollie_subscription_product;
            this._mollieSubscriptionIntervalAmount = mollieFieldsSubscription.mollie_subscription_interval_amount;
            this._mollieSubscriptionIntervalType = mollieFieldsSubscription.mollie_subscription_interval_type;
            this._mollieSubscriptionRepetitionAmount = mollieFieldsSubscription.mollie_subscription_repetition_amount;
            this._mollieSubscriptionRepetitionType = mollieFieldsSubscription.mollie_subscription_repetition_type;
        }
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
    getMollieSubscriptionProduct() {

        const boolType = this._mollieSubscriptionProduct;

        if (!boolType) {
            return false;
        }

        return boolType;
    }

    /**
     * @returns {*}
     */
    getMollieSubscriptionIntervalAmount() {
        if (!this._mollieSubscriptionIntervalAmount) {
            return '';
        }

        return this._mollieSubscriptionIntervalAmount;
    }

    /**
     * @returns {*}
     */
    getMollieSubscriptionIntervalType() {

        const stringType = this._mollieSubscriptionIntervalType + '';

        if (stringType !== 'days' || stringType !== 'weeks' || stringType !== 'months') {
            return '';
        }

        return stringType;
    }

    /**
     * @returns {*}
     */
    getMollieSubscriptionRepetitionAmount() {
        if (!this._mollieSubscriptionRepetitionAmount) {
            return '';
        }

        return this._mollieSubscriptionRepetitionAmount;
    }

    /**
     * @returns {*}
     */
    getMollieSubscriptionRepetitionType() {

        const stringType = this._mollieSubscriptionRepetitionType + '';

        if (stringType !== 'times' || stringType !== 'infinite') {
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
    setMollieSubscriptionProduct(value) {
        this._mollieSubscriptionProduct = value;
    }

    /**
     * @param value
     */
    setMollieSubscriptionIntervalAmount(value) {
        this._mollieSubscriptionIntervalAmount = value;
    }

    /**
     * @param value
     */
    setMollieSubscriptionIntervalType(value) {
        this._mollieSubscriptionIntervalType = value;
    }

    /**
     * @param value
     */
    setMollieSubscriptionRepetitionAmount(value) {
        this._mollieSubscriptionRepetitionAmount = value;
    }

    /**
     * @param value
     */
    setMollieSubscriptionRepetitionType(value) {
        this._mollieSubscriptionRepetitionType = value;
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
    clearMollieSubscriptionIntervalAmount() {
        this._mollieSubscriptionIntervalAmount = '';
    }

    /**
     *
     */
    clearMollieSubscriptionIntervalType() {
        this._mollieSubscriptionIntervalType = '';
    }

    /**
     *
     */
    clearMollieSubscriptionRepetitionAmount() {
        this._mollieSubscriptionRepetitionAmount = '';
    }

    /**
     *
     */
    clearMollieSubscriptionRepetitionType() {
        this._mollieSubscriptionRepetitionType = '';
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

        return mollie;
    }

    /**
     *
     * @returns {string[]}
     */
    toArraySubscription() {
        const mollie = {};

        if (this._mollieSubscriptionProduct) {
            mollie['mollie_subscription_product'] = this._mollieSubscriptionProduct;
        }
        if (this._mollieSubscriptionIntervalAmount !== '') {
            mollie['mollie_subscription_interval_amount'] = this._mollieSubscriptionIntervalAmount;
        }
        if (this._mollieSubscriptionIntervalType !== '') {
            mollie['mollie_subscription_interval_type'] = this._mollieSubscriptionIntervalType;
        }
        if (this._mollieSubscriptionRepetitionAmount !== '') {
            mollie['mollie_subscription_repetition_amount'] = this._mollieSubscriptionRepetitionAmount;
        }
        if (this._mollieSubscriptionRepetitionType !== '') {
            mollie['mollie_subscription_repetition_type'] = this._mollieSubscriptionRepetitionType;
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

        return false;
    }

    /**
     *
     * @returns {boolean}
     */
    hasSubscriptionData() {
        if (this._mollieSubscriptionProduct) {
            return true;
        }

        return false;
    }
}
