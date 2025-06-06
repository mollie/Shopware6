export default class CartRepository {

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getApplePayDirectButton() {
        return cy.get('.mollie-apple-pay-direct-cart > div > .js-apple-pay');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getPayPalExpressButton(){
        return cy.get('.mollie-paypal-express-cart button[name="paypal-express"]');
    }

    getDataPrivacyCheckbox() {
        return cy.get('#acceptedDataProtection');
    }

}
