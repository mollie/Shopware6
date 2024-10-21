export default class RegisterRepository {


    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getPayPalExpressButton(){
        return cy.get('.mollie-paypal-express-register button[name="paypal-express"]');
    }
}