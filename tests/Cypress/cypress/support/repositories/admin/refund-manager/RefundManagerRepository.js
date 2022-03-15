export default class RefundManagerRepository {


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
    getVerifyCheckbox() {
        return cy.get('.mollie-refund-manager-summary-container > .sw-field--checkbox > .sw-field--checkbox__content > .sw-field > .sw-field__label > label');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getDescription() {
        return cy.get('#sw-field--refundDescription');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getRefundButton() {
        return cy.get('.sw-button--contrast');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getFullRefundButton() {
        return cy.get('.sw-button--danger');
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
    getFirstRefundDescriptionLabel() {
        return cy.get('.sw-data-grid__cell--description > .sw-data-grid__cell-content');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getFirstRefundContextButton() {
        return cy.get(':nth-child(3) > .sw-card > .sw-card__content > .sw-data-grid > .sw-data-grid__wrapper > .sw-data-grid__table > .sw-data-grid__body > .sw-data-grid__row > .sw-data-grid__cell--actions > .sw-data-grid__cell-content > .sw-context-button > .sw-context-button__button');
    }

    /**
     *
     * @returns {Cypress.Chainable<undefined>}
     */
    getFirstRefundCancelButton() {
        return cy.contains('Cancel this refund');
    }

}
