export default class ConfirmRepository {

    /**
     *
     * @returns {*}
     */
    getSwitchPaymentMethodsButton() {
        return cy.get('.confirm-payment > .card > .card-body > [data-toggle="modal"]');
    }

    /**
     *
     * @returns {*}
     */
    getTerms() {
        return cy.get('.checkout-confirm-tos-label');
    }

    /**
     *
     * @returns {*}
     */
    getShowMorePaymentButtonsLabel() {
        return cy.get('.confirm-checkout-collapse-trigger-label');
    }

    /**
     *
     * @returns {*}
     */
    getTotalSum() {
        return cy.get('body > main > div > div > div > div > div.checkout-aside > div > div.checkout-aside-summary > div > div > dl > dd.col-5.checkout-aside-summary-value.checkout-aside-summary-total');
    }

    /**
     *
     * @returns {*}
     */
    getSubmitButton() {
        return cy.get('#confirmFormSubmit');
    }

}
