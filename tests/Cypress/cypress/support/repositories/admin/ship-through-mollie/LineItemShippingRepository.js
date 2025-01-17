export default class LineItemShippingRepository {

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getShippedQuantity() {
        return cy.get('.sw-modal__body > .sw-container > .sw-description-list > :nth-child(2)')
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getShippableQuantity() {
        return cy.get('.sw-modal__body > .sw-container > .sw-description-list > :nth-child(4)')
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getInputQuantity() {
        return cy.get('.cy-ship-quantity input');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getShippingButton() {
        return cy.get('.sw-modal__footer > .sw-button--primary');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getCancelButton() {
        return cy.get('.sw-modal__footer > :nth-child(1)');
    }

}
