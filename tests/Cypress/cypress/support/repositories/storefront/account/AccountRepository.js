export default class AccountRepository {

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSideMenuPaymentMethods() {
        return cy.get('.account-aside > .card > .list-group > [href="/account/payment"]');
    }

}
