export default class ProductDetailRepository {

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSaveButton() {
        return cy.get('.sw-button-process');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getMollieTab() {
        return cy.get('.product-tab-mollie');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getVoucherTypeDropdown() {
        return cy.get('.mollie-voucher-type > div > select');
    }

}
