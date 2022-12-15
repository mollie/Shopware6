export default class PDPRepository {

    /**
     *
     * @returns {*}
     */
    getAddToCartButton() {
        return cy.get('.buy-widget-container > .col-8 > .btn');
    }

    /**
     *
     * @returns {*}
     */
    getQuantity() {
        return cy.get('.col-4 > .custom-select');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getApplePayDirectButton() {
        return cy.get('.mollie-apple-pay-direct-pdp > div > .js-apple-pay');
    }

}
