export default class ProductAttributes {

    /**
     *
     * @param productEntity
     */
    constructor(productEntity) {

        this._voucherType = '';


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

        this._voucherType = mollieFields.voucher_type;
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
     *
     * @param value
     */
    setVoucherType(value) {
        this._voucherType = value;
    }

    /**
     *
     */
    clearVoucherType() {
        this._voucherType = '';
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
     * @returns {boolean}
     */
    hasData() {

        if (this._voucherType !== '') {
            return true;
        }

        return false;
    }

}
