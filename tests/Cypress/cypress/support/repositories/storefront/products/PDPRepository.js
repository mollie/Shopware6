import Shopware from "Services/shopware/Shopware";

const shopware = new Shopware();

export default class PDPRepository {

    /**
     *
     * @returns {*}
     */
    getAddToCartButton() {
        if (shopware.isVersionGreaterEqual('6.5')) {
            return cy.get('.btn-buy');
        } else {
            return cy.get('.buy-widget-container > .col-8 > .btn');
        }
    }

    /**
     *
     * @returns {*}
     */
    getQuantityDropdown() {
        return cy.get('.col-4 > .custom-select');
    }

    /**
     *
     * @returns {*}
     */
    getQuantityBtnUp() {
        return cy.get('.btn-plus');
    }

    /**
     *
     * @returns {Cypress.Chainable<JQuery<HTMLElement>>}
     */
    getApplePayDirectButton() {
        return cy.get('.mollie-apple-pay-direct-pdp > div > .js-apple-pay');
    }

}
