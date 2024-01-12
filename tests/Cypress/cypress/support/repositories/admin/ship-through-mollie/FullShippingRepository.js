export default class FullShippingRepository {


    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSelectAllItemsButton() {
        return cy.get('[style="grid-template-columns: 1fr 1fr 4fr; place-items: stretch;"] > :nth-child(1) > .sw-button__content');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getFirstItemSelectCheckbox() {
        return cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--itemselect > .sw-data-grid__cell-content input');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getShippingButton() {
        return cy.get('.btn-ship-order', {timeout: 10000});
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getFirstItemQuantity() {
        return cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--quantity > .sw-data-grid__cell-content');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSecondItemQuantity() {
        return cy.get('.sw-data-grid__row--1 > .sw-data-grid__cell--quantity > .sw-data-grid__cell-content');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getAvailableTrackingCodes() {
        return cy.get('[style="place-items: stretch;"] > :nth-child(1) > .sw-button > .sw-button__content');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getTrackingCarrier() {
        return cy.get('#sw-field--tracking-carrier');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getTrackingCode() {
        return cy.get('#sw-field--tracking-code');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getTrackingUrl() {
        return cy.get('#sw-field--tracking-url');
    }

}
