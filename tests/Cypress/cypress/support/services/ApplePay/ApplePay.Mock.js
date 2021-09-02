class ApplePaySessionMock {

    /**
     *
     * @returns {boolean}
     */
    completePayment() {
        return true
    }

    /**
     *
     * @returns {boolean}
     */
    completeMerchantValidation() {
        return true
    }

    /**
     *
     */
    begin() {
        if (this._onvalidatemerchant) {
            this._onvalidatemerchant(
                {validationURL: ''}
            )
        }

        if (this._onpaymentauthorized) {
            this._onpaymentauthorized(
                {payment: validPaymentRequestResponse(email)}
            )
        }
    }

    /**
     *
     * @param value
     */
    set onvalidatemerchant(value) {
        this._onvalidatemerchant = value
    }

    /**
     *
     * @param value
     */
    set onpaymentauthorized(value) {
        this._onpaymentauthorized = value
    }

}


/**
 *
 */
class ApplePaySessionMockFactory {

    /**
     * This function registered a mock directly
     * within the Cypress WIN of the browser.
     * @param available
     */
    registerApplePay(available) {
        const mock = this.buildMock(available);

        Cypress.on('window:before:load', (win) => {
            win.ApplePaySession = mock;
        })
    }

    /**
     * This function just builds a basic mock object
     * that can be used in any way
     * @param available
     * @returns {ApplePaySessionMock}
     */
    buildMock(available) {

        const mock = new ApplePaySessionMock();

        mock.canMakePayments = () => available;
        mock.supportsVersion = () => available;

        return mock;
    }

}


module.exports = {
    ApplePaySessionMock,
    ApplePaySessionMockFactory
}
