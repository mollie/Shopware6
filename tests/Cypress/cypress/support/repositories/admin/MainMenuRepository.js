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
        return cy.get('.sw-order > .sw-admin-menu__sub-navigation-list > .sw-admin-menu__navigation-list-item > .sw-admin-menu__navigation-link');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getCatalogues() {
        return cy.get('.sw-catalogue');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getProductsOverview() {
        return cy.get('.sw-product');
    }

}
