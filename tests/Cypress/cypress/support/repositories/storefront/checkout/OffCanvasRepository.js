export default class OffCanvasRepository {

    /**
     *
     * @returns {*}
     */
    getCartButton() {
        return cy.get('.offcanvas-cart-actions .btn-link');
    }

    /**
     *
     * @returns {*}
     */
    getCheckoutButton() {
        return cy.get('.begin-checkout-btn');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getApplePayDirectButton() {
        return cy.get('.mollie-apple-pay-direct-offcanvas > div > .js-apple-pay');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getPayPalExpressButton(){
        return cy.get('.mollie-paypal-express-offcanvas button[name="paypal-express"]');
    }
}
