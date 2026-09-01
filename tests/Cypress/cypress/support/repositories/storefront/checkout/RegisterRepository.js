export default class RegisterRepository {


    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getPayPalExpressButton(){
        return cy.get('.mollie-paypal-express-register button[name="paypal-express"]');
    }

    /**
     * The hidden field our template override injects when the cart contains a
     * subscription: it forces account creation and only exists in that case.
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getForcedCreateAccountInput() {
        return cy.get('input[type="hidden"][name="createCustomerAccount"][value="true"]');
    }

    /**
     * All "create a customer account" controls, regardless of visibility.
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getCreateAccountControls() {
        return cy.get('[name="createCustomerAccount"]');
    }

    /**
     * Only the "create a customer account" controls the customer can actually
     * interact with. In the subscription case the core checkbox is wrapped in a
     * hidden container and must not appear here.
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getVisibleCreateAccountControls() {
        return cy.get('[name="createCustomerAccount"]:visible');
    }

    /**
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getRegisterSubmitButton() {
        return cy.get('.register-submit > .btn, .register-submit .btn');
    }
}