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

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getSubscriptionToggle() {
        return cy.get('.mollie-subscription-enabled > div > div >input');
    }

}
