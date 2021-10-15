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
     * @returns {string}
     */
    getMollieTab() {
        return cy.contains('Mollie');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getVoucherTypeDropdown() {
        return cy.get('.mollie-voucher-type > div > select');
    }

}
