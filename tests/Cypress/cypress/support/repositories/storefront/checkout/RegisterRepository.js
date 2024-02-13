export default class RegisterRepository {


    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getPayPalExpressButton(){
        return cy.get('#molliePayPalExpressRegisterForm button[name="paypal-express"]');
    }
}