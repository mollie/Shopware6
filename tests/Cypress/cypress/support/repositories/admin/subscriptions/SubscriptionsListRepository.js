export default class SubscriptionsListRepository {

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getLatestSubscription() {
        return cy.get('.sw-data-grid__row--0');
    }

}
