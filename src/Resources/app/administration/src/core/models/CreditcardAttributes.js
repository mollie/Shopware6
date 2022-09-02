export default class CreditcardAttributes {

    /**
     *
     * @param mollieData
     */
    constructor(mollieData) {

        this._audience = '';
        this._countryCode = '';
        this._feeRegion = '';
        this._holder = '';
        this._label = '';
        this._number = '';
        this._security = '';

        if (mollieData === null) {
            return;
        }


        this._audience = this._convertString(mollieData['creditCardAudience']);
        this._countryCode = this._convertString(mollieData['creditCardCountryCode']);
        this._feeRegion = this._convertString(mollieData['creditCardFeeRegion']);
        this._holder = this._convertString(mollieData['creditCardHolder']);
        this._label = this._convertString(mollieData['creditCardLabel']);
        this._number = this._convertString(mollieData['creditCardNumber']);
        this._security = this._convertString(mollieData['creditCardSecurity']);

        return null;
    }

    /**
     * Helper method to decide if an object has credit card data.
     * @returns {boolean}
     */
    hasCreditCardData() {
        return (
            !!this._audience&&
            !!this._countryCode&&
            !!this._feeRegion&&
            !!this._holder&&
            !!this._label&&
            !!this._number&&
            !!this._security
        );
    }

    /**
     *
     * @returns {string|*}
     */
    getAudience() {
        return this._audience;
    }

    /**
     *
     * @returns {string|*}
     */
    getCountryCode() {
        return this._countryCode;
    }

    /**
     *
     * @returns {string|*}
     */
    getFeeRegion() {
        return this._feeRegion;
    }

    /**
     *
     * @returns {string|*}
     */
    getHolder() {
        return this._holder;
    }

    /**
     *
     * @returns {string|*}
     */
    getLabel() {
        return this._label;
    }

    /**
     *
     * @returns {string|*}
     */
    getNumber() {
        return this._number;
    }

    /**
     *
     * @returns {string|*}
     */
    getSecurity() {
        return this._security;
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
