export default class RefundManagerRepository {


    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getAmountField() {
        return cy.get('.refund-amount input');
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
        return cy.get('.refund-description textarea');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getInternalDescription() {
        return cy.get('.refund-internal-description textarea');
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
    getFirstLineItemQuantityInput() {
        return cy.get('.cy-input-quantity input').first();
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
    getFirstRefundPublicDescriptionLabel() {
        return cy.get('.sw-data-grid__cell--description > .sw-data-grid__cell-content');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getFirstRefundInternalDescriptionLabel() {
        return cy.get('.sw-data-grid__cell--internalDescription > .sw-data-grid__cell-content')
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getFirstRefundCompositionLabel() {
        return cy.get('.sw-data-grid__cell--composition > .sw-data-grid__cell-content');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getFirstRefundContextButton() {
        return cy.get('.mollie-refund-manager-refunds button[class=sw-context-button__button');
    }

    /**
     *
     * @returns {Cypress.Chainable<undefined>}
     */
    getFirstRefundCancelButton() {
        return cy.contains('Cancel this refund');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSelectAllItemsButton() {
        return cy.get('.order-container-top-left > .sw-button-group > :nth-child(1)');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getFirstRefundQuantityInput(){
        return cy.get('.cy-input-quantity input')
    }

}
