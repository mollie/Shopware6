import BasePaymentStatusScreen from "cypress-mollie/src/actions/screens/PaymentStatusScreen";

// the Mollie sandbox page sometimes loads slower than the default command timeout
const SCREEN_TIMEOUT = 30000;

export default class PaymentStatusScreen extends BasePaymentStatusScreen {

    selectOpen() {
        this._selectStatus('open');
    }

    selectPaid() {
        this._selectStatus('paid');
    }

    selectPending() {
        this._selectStatus('pending');
    }

    selectAuthorized() {
        this._selectStatus('authorized');
    }

    selectFailed() {
        this._selectStatus('failed');
    }

    selectCancelled() {
        this._selectStatus('canceled');
    }

    selectExpired() {
        this._selectStatus('expired');
    }

    _selectStatus(status) {
        cy.get('input[value="' + status + '"]', {timeout: SCREEN_TIMEOUT}).should('be.visible').click();
        cy.get('.button', {timeout: SCREEN_TIMEOUT}).should('be.visible').click();
    }
}
