export default class ProductAttributes {

    /**
     *
     * @param customFields
     */
    constructor(customFields) {

        this._voucherType = '';


        if (!customFields || !customFields.mollie_payments) {
            return;
        }

        const mollieFields = customFields.mollie_payments;

        this._voucherType = mollieFields.voucher_type;
    }

    /**
     *
     * @returns {*}
     */
    getVoucherType() {

        // we only allow values 1, 2, and 3
        // all other values are just empty
        if (this._voucherType < 1 || this._voucherType > 3) {
            return '';
        }

        return this._voucherType;
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
     * @returns {string[]}
     */
    toArray() {
        return {
            'voucher_type': this._voucherType,
        }
    }

}
