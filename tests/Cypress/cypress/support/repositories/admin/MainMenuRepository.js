export default class MainMenuRepository {

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getOrders() {
        return cy.get('.sw-order');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getOrdersOverview() {
        return cy.get('.sw-order-index > .sw-admin-menu__navigation-link');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSubscriptionsOverview() {
        return cy.get('.mollie-subscriptions > .sw-admin-menu__navigation-link > .sw-admin-menu__navigation-link-label');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getCatalogues() {
        return cy.get('.sw-catalogue', {timeout: 50000});
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getProductsOverview() {
        return cy.get('.sw-product');
    }

}
