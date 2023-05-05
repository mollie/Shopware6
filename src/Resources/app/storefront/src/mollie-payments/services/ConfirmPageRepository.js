export default class ConfirmPageRepository {

    /**
     *
     * @param document
     */
    constructor(document) {
        this._document = document;
    }

    /**
     *
     * @returns {*}
     */
    getPaymentForm() {
        return this._document.querySelector('#changePaymentForm');
    }

    /**
     *
     * @returns {*}
     */
    getConfirmForm() {
        return this._document.querySelector('#confirmOrderForm');
    }

    /**
     *
     * @returns {Element}
     */
    getSubmitButton() {
        let button = this._document.querySelector('#confirmFormSubmit');

        if (button === null) {
            button = this._document.querySelector('#confirmOrderForm > button[type="submit"]');
        }

        return button;
    }

}