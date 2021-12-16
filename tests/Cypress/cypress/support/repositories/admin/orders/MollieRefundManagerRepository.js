export default class OrdersListRepository {


    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getAmountField() {
        return cy.get('#sw-field--refundAmount');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSubmitButton() {
        return cy.get('.sw-modal__footer > .sw-button--primary');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getFirstRefundStatusLabel() {
        return cy.get('.sw-data-grid__cell--status > .sw-data-grid__cell-content > .sw-container');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getFirstRefundAmountLabel() {
        return cy.get('.sw-data-grid__cell--amount-value > .sw-data-grid__cell-content > .sw-container');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getFirstRefundMoreButton() {
        return cy.get('.sw-modal__body > .sw-container > .sw-data-grid > .sw-data-grid__wrapper > .sw-data-grid__table > .sw-data-grid__body > .sw-data-grid__row--0 > .sw-data-grid__cell--actions > .sw-data-grid__cell-content > .sw-context-button > .sw-context-button__button');
    }

    /**
     *
     * @returns {Cypress.Chainable<undefined>}
     */
    getFirstRefundCancelButton() {
        return cy.contains('Cancel this refund');
    }

}
