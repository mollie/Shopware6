export default class ShopConfiguration {

    constructor() {

        this._dataPrivacy = false;
    }

    /**
     * Sets the data privacy checkbox state
     * @param {boolean} value
     */
    setDataPrivacy(value) {
        this._dataPrivacy = value;
    }

    /**
     * Returns the data privacy checkbox state
     * @returns {boolean}
     */
    getDataPrivacy() {
        return this._dataPrivacy;
    }

}