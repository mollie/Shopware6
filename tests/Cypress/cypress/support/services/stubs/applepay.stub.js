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
     *
     * @returns {ApplePaySessionMock}
     */
    buildMock() {

        const mock = new ApplePaySessionMock();

        mock.canMakePayments = () => true;
        mock.supportsVersion = () => true;

        return mock;
    }

}


module.exports = {
    ApplePaySessionMock,
    ApplePaySessionMockFactory
}
